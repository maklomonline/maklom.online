import { finalTerritoryScore, territoryScoring } from '../goscorer.js';

export function goBoard(gameId, initialBoard, myColor, boardSize, initialColor, initialKo, initCapturesBlack, initCapturesWhite, initialStatus, initialMoveNumber, komi, initialResult, confirmMove = false, initDeadStones = [], initConfirmedBlack = false, initConfirmedWhite = false) {
    const alreadyOver = initialStatus === 'finished' || initialStatus === 'aborted';
    const initialScoring = initialStatus === 'scoring';
    return {
        gameId,
        board: Array.isArray(initialBoard) ? initialBoard : Array(boardSize * boardSize).fill(0),
        myColor,
        boardSize,
        komi: komi || 0,
        currentColor: initialColor,
        koPoint: initialKo || null,
        lastMoveIdx: null,
        hoverIdx: null,
        capturesBlack: initCapturesBlack || 0,
        capturesWhite: initCapturesWhite || 0,
        errorMsg: '',
        gameResult: alreadyOver && initialResult ? `เกมจบแล้ว: ${initialResult}` : '',
        gameOver: alreadyOver,
        moveNumber: initialMoveNumber || 0,
        isMyTurn: false,
        scoringProposal: initialScoring,
        scoreBlack: 0,
        scoreWhite: 0,
        confirmMove,
        pendingCoord: null,
        pendingIdx: null,

        // Scoring phase state
        deadStones: (initDeadStones ?? []).map(([r, c]) => r * boardSize + c),
        scoreConfirmedBlack: initConfirmedBlack ?? false,
        scoreConfirmedWhite: initConfirmedWhite ?? false,
        territoryMap: Array(boardSize * boardSize).fill(0), // 0=neutral, 1=black, 2=white

        init() {
            this.isMyTurn = !initialScoring && !!this.myColor && this.myColor !== '' && this.currentColor === this.myColor;
            if (initialScoring) {
                this._computeScore(this.capturesBlack, this.capturesWhite, this.komi);
            }
            this.listenToChannel();

            window.addEventListener('clock-timeout', (e) => {
                if (this.gameOver) return;
                this._submitTimeout(e.detail.color);
            });
        },

        _boardTo2D() {
            const stones = [];
            for (let y = 0; y < this.boardSize; y++) {
                const row = [];
                for (let x = 0; x < this.boardSize; x++) {
                    row.push(this.board[y * this.boardSize + x]);
                }
                stones.push(row);
            }
            return stones;
        },

        _buildMarkedDead() {
            const markedDead = Array.from({ length: this.boardSize }, () => Array(this.boardSize).fill(false));
            this.deadStones.forEach(idx => {
                const row = Math.floor(idx / this.boardSize);
                const col = idx % this.boardSize;
                markedDead[row][col] = true;
            });
            return markedDead;
        },

        _computeScore(capturesBlack, capturesWhite, komiVal) {
            const stones = this._boardTo2D();
            const markedDead = this._buildMarkedDead();
            const scoring = territoryScoring(stones, markedDead);
            const result = finalTerritoryScore(stones, markedDead, capturesBlack, capturesWhite, komiVal);
            this.scoreBlack = result.black;
            this.scoreWhite = result.white;
            // Build flat territory map
            this.territoryMap = Array(this.boardSize * this.boardSize).fill(0);
            for (let y = 0; y < this.boardSize; y++) {
                for (let x = 0; x < this.boardSize; x++) {
                    const t = scoring[y][x].isTerritoryFor;
                    if (t === 1) this.territoryMap[y * this.boardSize + x] = 1;
                    else if (t === 2) this.territoryMap[y * this.boardSize + x] = 2;
                }
            }
        },

        _enterScoringProposal(boardState, capturesBlack, capturesWhite, komiVal, deadStones, confirmedBlack, confirmedWhite) {
            if (boardState) this.board.splice(0, this.board.length, ...boardState);
            this.scoringProposal = true;
            this.isMyTurn = false;
            this.deadStones = (deadStones ?? []).map(([r, c]) => r * this.boardSize + c);
            this.scoreConfirmedBlack = confirmedBlack ?? false;
            this.scoreConfirmedWhite = confirmedWhite ?? false;
            this._computeScore(capturesBlack, capturesWhite, komiVal);
            this.renderStones();
        },

        listenToChannel() {
            if (!window.Echo) return;

            window.Echo.private(`game.${this.gameId}`)
                .listen('MoveMade', (e) => {
                    this.applyMoveState(e);
                })
                .listen('PlayerPassed', (e) => {
                    this.currentColor = e.nextColor ?? (this.currentColor === 'black' ? 'white' : 'black');
                    this.isMyTurn = !!this.myColor && this.myColor === this.currentColor;
                    this.moveNumber = (this.moveNumber || 0) + 1;
                    if (e.blackTimeLeft !== undefined) {
                        this.$dispatch('clock-update', {
                            blackTimeLeft: e.blackTimeLeft,
                            whiteTimeLeft: e.whiteTimeLeft,
                            blackPeriodsLeft: e.blackPeriodsLeft,
                            whitePeriodsLeft: e.whitePeriodsLeft,
                            nextColor: e.nextColor,
                        });
                    }
                })
                .listen('ScoringPhaseStarted', (e) => {
                    this._enterScoringProposal(
                        e.boardState,
                        e.capturesBlack ?? this.capturesBlack,
                        e.capturesWhite ?? this.capturesWhite,
                        e.komi ?? this.komi,
                        e.deadStones ?? [],
                        e.scoreConfirmedBlack ?? false,
                        e.scoreConfirmedWhite ?? false
                    );
                })
                .listen('DeadStonesUpdated', (e) => {
                    this.deadStones = (e.deadStones ?? []).map(([r, c]) => r * this.boardSize + c);
                    this.scoreConfirmedBlack = e.scoreConfirmedBlack ?? false;
                    this.scoreConfirmedWhite = e.scoreConfirmedWhite ?? false;
                    this._computeScore(this.capturesBlack, this.capturesWhite, this.komi);
                    this.renderStones();
                })
                .listen('ScoreConfirmationUpdated', (e) => {
                    this.scoreConfirmedBlack = e.scoreConfirmedBlack ?? false;
                    this.scoreConfirmedWhite = e.scoreConfirmedWhite ?? false;
                })
                .listen('ScoringCancelled', (e) => {
                    this.scoringProposal = false;
                    this.scoreBlack = 0;
                    this.scoreWhite = 0;
                    this.deadStones = [];
                    this.scoreConfirmedBlack = false;
                    this.scoreConfirmedWhite = false;
                    this.territoryMap = Array(this.boardSize * this.boardSize).fill(0);
                    this.currentColor = e.nextColor;
                    this.isMyTurn = !!this.myColor && this.myColor === this.currentColor;
                    this.renderStones();
                })
                .listen('GameEnded', (e) => {
                    this._handleGameOver(e.result);
                });
        },

        applyMoveState(e) {
            if (e.boardState) this.board.splice(0, this.board.length, ...e.boardState);
            if (e.koPoint !== undefined) this.koPoint = e.koPoint;
            this.currentColor = e.nextColor ?? (this.currentColor === 'black' ? 'white' : 'black');
            if (e.capturesBlack !== undefined) this.capturesBlack = e.capturesBlack;
            if (e.capturesWhite !== undefined) this.capturesWhite = e.capturesWhite;
            if (e.moveNumber !== undefined) this.moveNumber = e.moveNumber;
            this.isMyTurn = !!this.myColor && this.myColor === this.currentColor;
            this.pendingCoord = null;
            this.pendingIdx = null;
            this.errorMsg = '';

            if (e.coordinate) {
                const [row, col] = this.coordToRowCol(e.coordinate);
                this.lastMoveIdx = row * this.boardSize + col;
            }
        },

        // ── Click / Hover ─────────────────────────────
        handleBoardClick(event) {
            if (this.gameOver) return;
            // In scoring mode, clicks toggle dead groups
            if (this.scoringProposal) {
                this._handleScoringClick(event);
                return;
            }
            if (!this.isMyTurn) return;
            const { col, row } = this.svgCoords(event);
            if (row < 0 || row >= this.boardSize || col < 0 || col >= this.boardSize) return;
            const idx = row * this.boardSize + col;
            if (this.board[idx] !== 0) return;
            const coord = this.rowColToCoord(row, col);
            if (!this.confirmMove) {
                this.makeMove(coord);
                return;
            }
            // confirm mode: first click = pending, second click on same = confirm
            if (this.pendingIdx === idx) {
                this.confirmPendingMove();
            } else {
                this.pendingCoord = coord;
                this.pendingIdx = idx;
                this.errorMsg = '';
                this.renderStones();
            }
        },

        _handleScoringClick(event) {
            if (!this.myColor) return; // spectators cannot toggle
            const { col, row } = this.svgCoords(event);
            if (row < 0 || row >= this.boardSize || col < 0 || col >= this.boardSize) return;
            const idx = row * this.boardSize + col;
            if (this.board[idx] === 0) return; // empty intersection
            this.toggleDeadGroup(this.rowColToCoord(row, col));
        },

        confirmPendingMove() {
            if (!this.pendingCoord) return;
            const coord = this.pendingCoord;
            this.pendingCoord = null;
            this.pendingIdx = null;
            this.makeMove(coord);
        },

        cancelPendingMove() {
            this.pendingCoord = null;
            this.pendingIdx = null;
            this.renderStones();
        },

        handleBoardHover(event) {
            if (this.scoringProposal || !this.isMyTurn || this.gameOver) { this.hoverIdx = null; return; }
            const { col, row } = this.svgCoords(event);
            if (row < 0 || row >= this.boardSize || col < 0 || col >= this.boardSize) {
                this.hoverIdx = null;
            } else {
                this.hoverIdx = row * this.boardSize + col;
            }
        },

        svgCoords(event) {
            const svg = event.currentTarget.querySelector('svg');
            if (!svg) return { col: -1, row: -1 };
            const rect = svg.getBoundingClientRect();
            const viewBoxSize = (this.boardSize + 1) * 36; // matches PHP $cell=36
            const scale = rect.width / viewBoxSize;
            const x = (event.clientX - rect.left) / scale;
            const y = (event.clientY - rect.top) / scale;
            return {
                col: Math.round(x / 36) - 1,
                row: Math.round(y / 36) - 1,
            };
        },

        // ── Actions ───────────────────────────────────
        async makeMove(coordinate) {
            this.errorMsg = '';
            try {
                const { data } = await window.axios.post(`/games/${this.gameId}/move`, { coordinate });
                this.applyMoveState({
                    boardState: data.boardState,
                    koPoint: data.koPoint,
                    nextColor: data.currentColor,
                    capturesBlack: data.capturesBlack,
                    capturesWhite: data.capturesWhite,
                    moveNumber: data.moveNumber,
                    coordinate,
                });
                this.$dispatch('clock-sync', {
                    blackTimeLeft: data.blackTimeLeft,
                    whiteTimeLeft: data.whiteTimeLeft,
                    blackPeriodsLeft: data.blackPeriodsLeft,
                    whitePeriodsLeft: data.whitePeriodsLeft,
                    nextColor: data.currentColor,
                });
            } catch (err) {
                if (err.response?.data?.timeout) {
                    this._handleGameOver(err.response.data.result);
                } else {
                    this.errorMsg = err.response?.data?.error || 'เกิดข้อผิดพลาด';
                }
            }
        },

        async pass() {
            this.errorMsg = '';
            try {
                const { data } = await window.axios.post(`/games/${this.gameId}/pass`);
                this.currentColor = data.currentColor ?? (this.currentColor === 'black' ? 'white' : 'black');
                this.isMyTurn = !!this.myColor && this.myColor === this.currentColor;
                this.moveNumber = (this.moveNumber || 0) + 1;
                this.koPoint = null;

                if (data.status === 'scoring') {
                    this._enterScoringProposal(
                        data.boardState,
                        data.capturesBlack ?? this.capturesBlack,
                        data.capturesWhite ?? this.capturesWhite,
                        data.komi ?? this.komi,
                        [],
                        false,
                        false
                    );
                }

                this.$dispatch('clock-sync', {
                    blackTimeLeft: data.blackTimeLeft,
                    whiteTimeLeft: data.whiteTimeLeft,
                    blackPeriodsLeft: data.blackPeriodsLeft,
                    whitePeriodsLeft: data.whitePeriodsLeft,
                    nextColor: data.currentColor,
                });
            } catch (err) {
                if (err.response?.data?.timeout) {
                    this._handleGameOver(err.response.data.result);
                } else {
                    this.errorMsg = err.response?.data?.error || 'เกิดข้อผิดพลาด';
                }
            }
        },

        async toggleDeadGroup(coordinate) {
            this.errorMsg = '';
            try {
                const { data } = await window.axios.post(`/games/${this.gameId}/scoring/toggle-dead-group`, { coordinate });
                this.deadStones = (data.deadStones ?? []).map(([r, c]) => r * this.boardSize + c);
                this.scoreConfirmedBlack = data.scoreConfirmedBlack ?? false;
                this.scoreConfirmedWhite = data.scoreConfirmedWhite ?? false;
                this._computeScore(this.capturesBlack, this.capturesWhite, this.komi);
                this.renderStones();
            } catch (err) {
                this.errorMsg = err.response?.data?.error || 'เกิดข้อผิดพลาด';
            }
        },

        async _submitTimeout(color) {
            try {
                const { data } = await window.axios.post(`/games/${this.gameId}/timeout`, { color });
                this._handleGameOver(data.result);
            } catch (err) {
                if (err.response?.status === 422 && err.response?.data?.error === 'Player has not timed out yet') {
                    setTimeout(() => {
                        this._submitTimeout(color);
                    }, 1000);
                }
            }
        },

        _handleGameOver(result) {
            this.gameResult = `เกมจบแล้ว: ${result ?? ''}`;
            this.gameOver = true;
            this.scoringProposal = false;
            this.isMyTurn = false;
            this.$dispatch('game-over');
        },

        async confirmScore() {
            this.errorMsg = '';
            try {
                const { data } = await window.axios.post(`/games/${this.gameId}/confirm-score`);
                if (data.finished) {
                    this._handleGameOver(data.result);
                } else {
                    this.scoreConfirmedBlack = data.scoreConfirmedBlack ?? this.scoreConfirmedBlack;
                    this.scoreConfirmedWhite = data.scoreConfirmedWhite ?? this.scoreConfirmedWhite;
                }
            } catch (err) {
                this.errorMsg = err.response?.data?.error || 'เกิดข้อผิดพลาด';
            }
        },

        async cancelScoring() {
            this.errorMsg = '';
            try {
                const { data } = await window.axios.post(`/games/${this.gameId}/cancel-scoring`);
                this.scoringProposal = false;
                this.scoreBlack = 0;
                this.scoreWhite = 0;
                this.deadStones = [];
                this.scoreConfirmedBlack = false;
                this.scoreConfirmedWhite = false;
                this.territoryMap = Array(this.boardSize * this.boardSize).fill(0);
                this.currentColor = data.currentColor;
                this.isMyTurn = !!this.myColor && this.myColor === this.currentColor;
                this.renderStones();
            } catch (err) {
                this.errorMsg = err.response?.data?.error || 'เกิดข้อผิดพลาด';
            }
        },

        async resignConfirm() {
            if (!window.confirm('ยืนยันการยอมแพ้?')) return;
            this.errorMsg = '';
            try {
                const { data } = await window.axios.post(`/games/${this.gameId}/resign`);
                this._handleGameOver(data.result);
            } catch (err) {
                this.errorMsg = err.response?.data?.error || 'เกิดข้อผิดพลาด';
            }
        },

        // Returns true if current player has already confirmed score
        myConfirmed() {
            if (this.myColor === 'black') return this.scoreConfirmedBlack;
            if (this.myColor === 'white') return this.scoreConfirmedWhite;
            return false;
        },

        // ── Stone rendering (bypasses Alpine x-for SVG namespace issues) ─
        renderStones() {
            const layer = this.$refs.stonesLayer;
            if (!layer) return;
            while (layer.firstChild) layer.removeChild(layer.firstChild);
            const ns = 'http://www.w3.org/2000/svg';
            const C = 36; // cell size px

            // pending stone (confirm mode)
            if (this.pendingIdx !== null) {
                const px = (this.pendingIdx % this.boardSize + 1) * C;
                const py = (Math.floor(this.pendingIdx / this.boardSize) + 1) * C;
                const pg = document.createElementNS(ns, 'g');
                pg.setAttribute('style', 'pointer-events:none');
                const pstone = document.createElementNS(ns, 'circle');
                pstone.setAttribute('cx', px);
                pstone.setAttribute('cy', py);
                pstone.setAttribute('r', C * 0.44);
                pstone.setAttribute('fill', this.myColor === 'black' ? '#1C1C1C' : '#F0EAD0');
                pstone.setAttribute('stroke', this.myColor === 'black' ? '#000' : '#B8A880');
                pstone.setAttribute('stroke-width', '2.5');
                pstone.setAttribute('opacity', '0.55');
                pg.appendChild(pstone);
                // pulsing ring
                const ring = document.createElementNS(ns, 'circle');
                ring.setAttribute('cx', px);
                ring.setAttribute('cy', py);
                ring.setAttribute('r', C * 0.48);
                ring.setAttribute('fill', 'none');
                ring.setAttribute('stroke', '#4F46E5');
                ring.setAttribute('stroke-width', '2');
                ring.setAttribute('opacity', '0.8');
                pg.appendChild(ring);
                layer.appendChild(pg);
            }

            const deadSet = new Set(this.deadStones);

            this.board.forEach((val, idx) => {
                if (val === 0) return;
                const cx = (idx % this.boardSize + 1) * C;
                const cy = (Math.floor(idx / this.boardSize) + 1) * C;
                const isDead = deadSet.has(idx);
                const g = document.createElementNS(ns, 'g');
                g.setAttribute('style', 'pointer-events:none');
                // shadow
                const shadow = document.createElementNS(ns, 'circle');
                shadow.setAttribute('cx', cx + 1.5);
                shadow.setAttribute('cy', cy + 2);
                shadow.setAttribute('r', C * 0.44);
                shadow.setAttribute('fill', 'rgba(0,0,0,0.25)');
                g.appendChild(shadow);
                // stone body
                const stone = document.createElementNS(ns, 'circle');
                stone.setAttribute('cx', cx);
                stone.setAttribute('cy', cy);
                stone.setAttribute('r', C * 0.44);
                stone.setAttribute('fill', val === 1 ? '#1C1C1C' : '#F0EAD0');
                stone.setAttribute('stroke', val === 1 ? '#000' : '#B8A880');
                stone.setAttribute('stroke-width', '1.5');
                if (isDead) stone.setAttribute('opacity', '0.35');
                g.appendChild(stone);
                // shine (black only, not dead)
                if (val === 1 && !isDead) {
                    const shine = document.createElementNS(ns, 'circle');
                    shine.setAttribute('cx', cx - C * 0.12);
                    shine.setAttribute('cy', cy - C * 0.12);
                    shine.setAttribute('r', C * 0.11);
                    shine.setAttribute('fill', 'rgba(255,255,255,0.35)');
                    g.appendChild(shine);
                }
                // last move marker (not in scoring mode)
                if (!this.scoringProposal && this.lastMoveIdx === idx) {
                    const marker = document.createElementNS(ns, 'circle');
                    marker.setAttribute('cx', cx);
                    marker.setAttribute('cy', cy);
                    marker.setAttribute('r', C * 0.2);
                    marker.setAttribute('fill', val === 1 ? '#fff' : '#555');
                    g.appendChild(marker);
                }
                layer.appendChild(g);
            });

            // Scoring overlays
            if (this.scoringProposal) {
                // Territory markers on empty intersections
                for (let idx = 0; idx < this.boardSize * this.boardSize; idx++) {
                    const terr = this.territoryMap[idx];
                    if (terr === 0) continue;
                    if (this.board[idx] !== 0 && !deadSet.has(idx)) continue; // occupied by live stone
                    const cx = (idx % this.boardSize + 1) * C;
                    const cy = (Math.floor(idx / this.boardSize) + 1) * C;
                    const half = C * 0.16;
                    const dot = document.createElementNS(ns, 'rect');
                    dot.setAttribute('x', cx - half);
                    dot.setAttribute('y', cy - half);
                    dot.setAttribute('width', half * 2);
                    dot.setAttribute('height', half * 2);
                    dot.setAttribute('fill', terr === 1 ? '#111' : '#f5f0e0');
                    dot.setAttribute('stroke', terr === 1 ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.25)');
                    dot.setAttribute('stroke-width', '0.5');
                    dot.setAttribute('opacity', '0.85');
                    dot.setAttribute('style', 'pointer-events:none');
                    layer.appendChild(dot);
                }

                // Dead stone X overlays
                deadSet.forEach(idx => {
                    if (this.board[idx] === 0) return;
                    const cx = (idx % this.boardSize + 1) * C;
                    const cy = (Math.floor(idx / this.boardSize) + 1) * C;
                    const r = C * 0.28;
                    const g = document.createElementNS(ns, 'g');
                    g.setAttribute('style', 'pointer-events:none');
                    const x1 = document.createElementNS(ns, 'line');
                    x1.setAttribute('x1', cx - r); x1.setAttribute('y1', cy - r);
                    x1.setAttribute('x2', cx + r); x1.setAttribute('y2', cy + r);
                    x1.setAttribute('stroke', '#EF4444'); x1.setAttribute('stroke-width', '2.5');
                    x1.setAttribute('stroke-linecap', 'round');
                    g.appendChild(x1);
                    const x2 = document.createElementNS(ns, 'line');
                    x2.setAttribute('x1', cx + r); x2.setAttribute('y1', cy - r);
                    x2.setAttribute('x2', cx - r); x2.setAttribute('y2', cy + r);
                    x2.setAttribute('stroke', '#EF4444'); x2.setAttribute('stroke-width', '2.5');
                    x2.setAttribute('stroke-linecap', 'round');
                    g.appendChild(x2);
                    layer.appendChild(g);
                });
            }
        },

        // ── Helpers ───────────────────────────────────
        isLastMove(idx) {
            return this.lastMoveIdx === idx;
        },

        rowColToCoord(row, col) {
            const letters = 'ABCDEFGHJKLMNOPQRST';
            return letters[col] + (this.boardSize - row);
        },

        coordToRowCol(coord) {
            if (!coord) return [0, 0];
            const letters = 'ABCDEFGHJKLMNOPQRST';
            const col = letters.indexOf(coord[0].toUpperCase());
            const row = this.boardSize - parseInt(coord.slice(1));
            return [row, col];
        },
    };
}
