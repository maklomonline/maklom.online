#!/usr/bin/env python3
"""
Maklom Bot Client
=================
เชื่อมต่อ GTP engine (เช่น KataGo, GNU Go, Leela Zero) กับ Maklom bot server

ความต้องการ:
  Python 3.8+
  pip install requests

ตัวอย่างการใช้งาน:
  python bot_client.py \
    --server https://maklom.example.com \
    --username my_katago_bot \
    --password secretpassword \
    --engine /usr/local/bin/katago \
    --engine-args "gtp -config /path/to/default_gtp.cfg -model /path/to/model.bin.gz"
"""

import argparse
import json
import logging
import shlex
import subprocess
import sys
import threading
import time
from typing import Optional

import requests

# ─── Logging ──────────────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
)
log = logging.getLogger("maklom_bot")


# ─── GTP Engine wrapper ───────────────────────────────────────────────────────

class GTPEngine:
    """สื่อสารกับ GTP engine ผ่าน stdin/stdout"""

    def __init__(self, engine_path: str, engine_args: str):
        cmd = [engine_path] + shlex.split(engine_args)
        log.info(f"Starting GTP engine: {' '.join(cmd)}")
        self._proc = subprocess.Popen(
            cmd,
            stdin=subprocess.PIPE,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            bufsize=1,
        )
        self._lock = threading.Lock()
        self._seq = 0

    def _send(self, command: str) -> str:
        """ส่ง GTP command และรับผลลัพธ์"""
        with self._lock:
            self._seq += 1
            cmd_id = self._seq
            line = f"{cmd_id} {command}\n"
            self._proc.stdin.write(line)
            self._proc.stdin.flush()

            response_lines = []
            while True:
                out = self._proc.stdout.readline()
                if not out:
                    raise RuntimeError("GTP engine closed unexpectedly")
                out = out.rstrip("\n")
                if out == "":
                    break
                response_lines.append(out)

            full = "\n".join(response_lines)
            if full.startswith(f"= ") or full.startswith(f"={cmd_id} "):
                return full.lstrip(f"= ").lstrip(f"={cmd_id} ").strip()
            elif full.startswith("? ") or full.startswith(f"?{cmd_id} "):
                raise ValueError(full)
            return full.strip()

    def clear_board(self, board_size: int) -> None:
        self._send(f"boardsize {board_size}")
        self._send("clear_board")

    def set_komi(self, komi: float) -> None:
        self._send(f"komi {komi}")

    def set_time_settings(self, main_time: int) -> None:
        """ตั้งค่าเวลา (แบบง่ายๆ สำหรับ GTP time_settings)"""
        self._send(f"time_settings {main_time} 0 0")

    def place_handicap(self, stones: list) -> None:
        """วางหมากฮันดิแคป"""
        if stones:
            stone_list = " ".join(stones)
            self._send(f"set_free_handicap {stone_list}")

    def play(self, color: str, coordinate: str) -> None:
        """บอกให้ engine รู้ว่าฝ่ายตรงข้ามวางหมากที่ใด"""
        gtp_color = "B" if color == "black" else "W"
        self._send(f"play {gtp_color} {coordinate.upper()}")

    def genmove(self, color: str) -> str:
        """ขอให้ engine คิดหมาก"""
        gtp_color = "B" if color == "black" else "W"
        result = self._send(f"genmove {gtp_color}")
        return result.strip()

    def quit(self) -> None:
        try:
            self._send("quit")
        except Exception:
            pass
        self._proc.terminate()

    def is_alive(self) -> bool:
        return self._proc.poll() is None


# ─── Board state → GTP moves ─────────────────────────────────────────────────

def board_state_to_gtp_moves(board_state: list, board_size: int, handicap: int, moves_history: list) -> list:
    """
    สร้างรายการ GTP play commands จาก board_state (snapshot)
    ใช้ moves_history ถ้ามี (จะแม่นยำกว่า)
    """
    cols = "ABCDEFGHJKLMNOPQRST"

    def idx_to_gtp(row: int, col: int, size: int) -> str:
        return f"{cols[col]}{size - row}"

    moves = []
    if moves_history:
        for mv in moves_history:
            color = mv.get("color", "black")
            coord = mv.get("coordinate", "pass")
            moves.append((color, coord.upper() if coord and coord.lower() != "pass" else "pass"))
    return moves


# ─── Maklom API client ────────────────────────────────────────────────────────

class MaklomsAPI:
    def __init__(self, server_url: str):
        self.base = server_url.rstrip("/") + "/api/bot"
        self.token: Optional[str] = None
        self.session = requests.Session()
        self.session.headers.update({"Accept": "application/json", "Content-Type": "application/json"})

    def _auth_headers(self) -> dict:
        return {"Authorization": f"Bearer {self.token}"}

    def login(self, username: str, password: str) -> bool:
        try:
            r = self.session.post(f"{self.base}/auth", json={"username": username, "password": password}, timeout=10)
            r.raise_for_status()
            data = r.json()
            self.token = data["token"]
            log.info(f"Logged in as {data['display_name']} [{data['rank']}]")
            return True
        except requests.HTTPError as e:
            log.error(f"Login failed: {e.response.text}")
            return False
        except Exception as e:
            log.error(f"Login error: {e}")
            return False

    def heartbeat(self) -> None:
        try:
            self.session.post(f"{self.base}/heartbeat", headers=self._auth_headers(), timeout=5)
        except Exception as e:
            log.warning(f"Heartbeat failed: {e}")

    def go_offline(self) -> None:
        try:
            self.session.post(f"{self.base}/offline", headers=self._auth_headers(), timeout=5)
        except Exception:
            pass

    def get_challenges(self) -> list:
        try:
            r = self.session.get(f"{self.base}/challenges", headers=self._auth_headers(), timeout=10)
            r.raise_for_status()
            return r.json().get("challenges", [])
        except Exception as e:
            log.warning(f"Failed to get challenges: {e}")
            return []

    def accept_challenge(self, challenge_id: int) -> Optional[dict]:
        try:
            r = self.session.post(
                f"{self.base}/challenges/{challenge_id}/accept",
                headers=self._auth_headers(), timeout=10
            )
            r.raise_for_status()
            return r.json()
        except Exception as e:
            log.warning(f"Failed to accept challenge {challenge_id}: {e}")
            return None

    def get_active_games(self) -> tuple[list, list]:
        """Returns (active_games, scoring_games)"""
        try:
            r = self.session.get(f"{self.base}/games", headers=self._auth_headers(), timeout=10)
            r.raise_for_status()
            data = r.json()
            return data.get("games", []), data.get("scoring_games", [])
        except Exception as e:
            log.warning(f"Failed to get games: {e}")
            return [], []

    def get_game_state(self, game_id: int) -> Optional[dict]:
        try:
            r = self.session.get(
                f"{self.base}/games/{game_id}",
                headers=self._auth_headers(), timeout=10
            )
            r.raise_for_status()
            return r.json()
        except Exception as e:
            log.warning(f"Failed to get game {game_id}: {e}")
            return None

    def make_move(self, game_id: int, coordinate: str) -> Optional[dict]:
        try:
            r = self.session.post(
                f"{self.base}/games/{game_id}/move",
                json={"coordinate": coordinate},
                headers=self._auth_headers(), timeout=30
            )
            r.raise_for_status()
            return r.json()
        except Exception as e:
            log.warning(f"Failed to make move in game {game_id}: {e}")
            return None

    def pass_move(self, game_id: int) -> Optional[dict]:
        try:
            r = self.session.post(
                f"{self.base}/games/{game_id}/pass",
                headers=self._auth_headers(), timeout=15
            )
            r.raise_for_status()
            return r.json()
        except Exception as e:
            log.warning(f"Failed to pass in game {game_id}: {e}")
            return None

    def confirm_score(self, game_id: int) -> Optional[dict]:
        try:
            r = self.session.post(
                f"{self.base}/games/{game_id}/confirm-score",
                headers=self._auth_headers(), timeout=15
            )
            r.raise_for_status()
            return r.json()
        except Exception as e:
            log.warning(f"Failed to confirm score in game {game_id}: {e}")
            return None

    def submit_dead_stones(self, game_id: int, dead_stones: list) -> bool:
        try:
            r = self.session.post(
                f"{self.base}/games/{game_id}/scoring/dead-stones",
                json={"dead_stones": dead_stones},
                headers=self._auth_headers(), timeout=15
            )
            r.raise_for_status()
            return True
        except Exception as e:
            log.warning(f"Failed to submit dead stones for game {game_id}: {e}")
            return False


# ─── Bot Game Manager ─────────────────────────────────────────────────────────

class GameSession:
    """จัดการ GTP engine instance ต่อ 1 เกม"""

    def __init__(self, game_info: dict, engine_path: str, engine_args: str):
        self.game_id = game_info["id"]
        self.bot_color = game_info["bot_color"]
        self.board_size = game_info["board_size"]
        self.komi = float(game_info["komi"])
        self.handicap = int(game_info.get("handicap", 0))
        self.move_number = game_info.get("move_number", 0)

        self.engine = GTPEngine(engine_path, engine_args)
        self.engine.clear_board(self.board_size)
        self.engine.set_komi(self.komi)

        self._initialized = False
        log.info(f"[Game {self.game_id}] Session created, playing as {self.bot_color}")

    def initialize_from_state(self, game_state: dict) -> None:
        """ตั้งค่า board state จาก server"""
        if self._initialized:
            return

        board_size = game_state["board_size"]
        board = game_state.get("board_state", [])

        # Re-initialize engine
        self.engine.clear_board(board_size)
        self.engine.set_komi(float(game_state["komi"]))

        # ถ้ามี board_state ให้ reconstruct ด้วยการ play moves
        # แต่ถ้าไม่มี history ให้ใช้ตั้งค่าจาก snapshot โดยตรง
        # (สำหรับความง่าย ขอใช้ GTP play_board ถ้ามี หรือ reconstruct จาก board array)
        if board:
            self._reconstruct_board(board, board_size)

        self._initialized = True

    def _reconstruct_board(self, board_state: list, board_size: int) -> None:
        """
        Reconstruct board จาก flat array:
        0 = empty, 1 = black, 2 = white
        วิธีนี้ไม่แม่นยำ 100% (เนื่องจากไม่รู้ order ของหมาก)
        แต่เพียงพอสำหรับการคำนวณหมากถัดไป
        """
        cols = "ABCDEFGHJKLMNOPQRST"
        # ใช้ load_sgf หรือ play ตามลำดับ
        # วิธีง่ายที่สุด: ใช้ GTP loadsgf ถ้า engine รองรับ
        # หรือ reconstruct ด้วย play (สลับสีเองก็ได้)
        black_stones = []
        white_stones = []
        for i, cell in enumerate(board_state):
            if cell == 0:
                continue
            row = i // board_size
            col = i % board_size
            gtp_coord = f"{cols[col]}{board_size - row}"
            if cell == 1:
                black_stones.append(gtp_coord)
            elif cell == 2:
                white_stones.append(gtp_coord)

        # ส่งด้วย set_position ถ้า engine รองรับ (KataGo รองรับ)
        # หรือ play สลับสีโดยประมาณ
        total = len(black_stones) + len(white_stones)
        if total == 0:
            return

        # สลับวางดำ/ขาวสลับกัน (ไม่แม่นยำ แต่ OK สำหรับ evaluation)
        all_moves = [(coord, "B") for coord in black_stones] + [(coord, "W") for coord in white_stones]
        for coord, color in all_moves:
            try:
                self.engine._send(f"play {color} {coord}")
            except Exception:
                pass

    def play_opponent_move(self, color: str, coordinate: str) -> None:
        """แจ้ง engine ว่าฝ่ายตรงข้ามวางหมาก"""
        try:
            self.engine.play(color, coordinate)
        except Exception as e:
            log.warning(f"[Game {self.game_id}] Failed to play opponent move {coordinate}: {e}")

    def generate_move(self) -> str:
        """ขอให้ engine คิดหมาก"""
        try:
            move = self.engine.genmove(self.bot_color)
            log.info(f"[Game {self.game_id}] Engine suggests: {move}")
            return move
        except Exception as e:
            log.error(f"[Game {self.game_id}] Engine error: {e}")
            return "pass"

    def close(self) -> None:
        try:
            self.engine.quit()
        except Exception:
            pass


# ─── Main Bot ─────────────────────────────────────────────────────────────────

class MaklomBot:
    def __init__(self, args: argparse.Namespace):
        self.api = MaklomsAPI(args.server)
        self.engine_path = args.engine
        self.engine_args = args.engine_args or ""
        self.poll_interval = args.poll_interval
        self.heartbeat_interval = args.heartbeat

        # game_id → GameSession
        self.sessions: dict[int, GameSession] = {}
        self._running = False
        self._last_heartbeat = 0.0

    def start(self, username: str, password: str) -> None:
        if not self.api.login(username, password):
            log.error("Cannot login. Exiting.")
            sys.exit(1)

        self._running = True
        log.info("Bot client started. Polling for games...")
        log.info("Press Ctrl+C to stop.")

        try:
            while self._running:
                now = time.time()

                # Heartbeat
                if now - self._last_heartbeat >= self.heartbeat_interval:
                    self.api.heartbeat()
                    self._last_heartbeat = now

                # Check challenges
                self._handle_challenges()

                # Play active games
                self._handle_games()

                time.sleep(self.poll_interval)

        except KeyboardInterrupt:
            log.info("Shutting down...")
        finally:
            self._cleanup()

    def _handle_challenges(self) -> None:
        """รับคำท้าดวลที่รอรับโดยอัตโนมัติ"""
        challenges = self.api.get_challenges()
        for challenge in challenges:
            cid = challenge["id"]
            log.info(f"Accepting challenge {cid} from {challenge['challenger']['display_name']} "
                     f"({challenge['board_size']}x{challenge['board_size']} {challenge['clock_type']})")
            result = self.api.accept_challenge(cid)
            if result:
                game_id = result.get("game_id")
                log.info(f"Challenge accepted. Game {game_id} created.")
                # ถ้าเป็นตาของ bot ทันที
                if result.get("my_turn") and game_id:
                    self._start_game_session(result)

    def _handle_games(self) -> None:
        """เล่นเกมที่ถึงตาของ bot"""
        active_games, scoring_games = self.api.get_active_games()

        for game_info in active_games:
            game_id = game_info["id"]
            if game_info.get("my_turn"):
                self._play_turn(game_id, game_info)

        for game_info in scoring_games:
            game_id = game_info["id"]
            self._handle_scoring(game_id)

        # Clean up finished sessions
        finished = [gid for gid, session in self.sessions.items()
                    if not session.engine.is_alive()]
        for gid in finished:
            log.info(f"[Game {gid}] Engine died, removing session")
            del self.sessions[gid]

    def _start_game_session(self, game_info: dict) -> GameSession:
        """สร้าง GameSession ใหม่"""
        game_id = game_info["id"]
        if game_id not in self.sessions:
            session = GameSession(game_info, self.engine_path, self.engine_args)

            # ดึง full state เพื่อ initialize board
            full_state = self.api.get_game_state(game_id)
            if full_state:
                session.initialize_from_state(full_state)

            self.sessions[game_id] = session
        return self.sessions[game_id]

    def _play_turn(self, game_id: int, game_info: dict) -> None:
        """เล่นหมากในตาของ bot"""
        if game_id not in self.sessions:
            session = self._start_game_session(game_info)
        else:
            session = self.sessions[game_id]

        move = session.generate_move()

        if move.upper() == "PASS" or move.lower() == "pass":
            log.info(f"[Game {game_id}] Passing")
            result = self.api.pass_move(game_id)
        elif move.upper() == "RESIGN" or move.lower() == "resign":
            log.info(f"[Game {game_id}] Resigning")
            # ไม่ resign อัตโนมัติ — pass แทน
            result = self.api.pass_move(game_id)
        else:
            log.info(f"[Game {game_id}] Playing {move}")
            result = self.api.make_move(game_id, move)

        if result and result.get("success"):
            updated_game = result.get("game", {})
            # ตรวจว่าเกมจบหรือยัง
            if updated_game.get("status") == "finished":
                log.info(f"[Game {game_id}] Game finished!")
                if game_id in self.sessions:
                    self.sessions[game_id].close()
                    del self.sessions[game_id]
        elif result is None:
            log.warning(f"[Game {game_id}] Move failed")

    def _handle_scoring(self, game_id: int) -> None:
        """ยืนยันคะแนนโดยอัตโนมัติ (ส่ง dead stones = [] แล้ว confirm)"""
        log.info(f"[Game {game_id}] Scoring phase — submitting dead stones and confirming")
        # ส่ง empty dead stones (ยอมรับการนับของ server)
        self.api.submit_dead_stones(game_id, [])
        time.sleep(0.5)
        result = self.api.confirm_score(game_id)
        if result and result.get("success"):
            log.info(f"[Game {game_id}] Score confirmed: {result.get('result')}")
            if game_id in self.sessions:
                self.sessions[game_id].close()
                del self.sessions[game_id]

    def _cleanup(self) -> None:
        log.info("Sending offline signal...")
        self.api.go_offline()
        for session in self.sessions.values():
            session.close()
        self.sessions.clear()
        log.info("Bot client stopped.")


# ─── Entry point ─────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="Maklom Bot Client — เชื่อมต่อ GTP engine กับ Maklom bot server",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument("--server",         required=True,  help="URL ของ Maklom server (เช่น https://maklom.example.com)")
    parser.add_argument("--username",       required=True,  help="ชื่อผู้ใช้บัญชีคอมพิวเตอร์")
    parser.add_argument("--password",       required=True,  help="รหัสผ่านบัญชีคอมพิวเตอร์")
    parser.add_argument("--engine",         required=True,  help="Path ของ GTP engine executable (เช่น /usr/bin/katago)")
    parser.add_argument("--engine-args",    default="",     help='Arguments สำหรับ GTP engine (string เดียว เช่น "gtp -config cfg.txt")')
    parser.add_argument("--poll-interval",  type=float, default=2.0,  help="ช่วงเวลา polling วินาที (default: 2)")
    parser.add_argument("--heartbeat",      type=float, default=30.0, help="ช่วงเวลา heartbeat วินาที (default: 30)")
    args = parser.parse_args()

    try:
        import requests  # noqa: F401
    except ImportError:
        print("ERROR: กรุณาติดตั้ง requests ก่อน: pip install requests")
        sys.exit(1)

    bot = MaklomBot(args)
    bot.start(args.username, args.password)


if __name__ == "__main__":
    main()
