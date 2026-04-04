/**
 * gameReview — Alpine.js component for navigating and annotating finished Go games.
 *
 * allBoardStates : array of board arrays (index 0 = initial, N = after move N)
 * moves          : array of { move_number, color, coordinate }
 * boardSize      : 9 | 13 | 19
 * komi           : float
 * annotations    : array of { id, title, user, user_id, sgf, created_at }
 * currentUserId  : int | null
 */
export function gameReview(gameId, allBoardStates, moves, boardSize, komi, annotations, currentUserId) {
    // Keep the large boardStates array outside Alpine's reactive proxy for performance.
    // Only the small `board` snapshot (current position) is reactive.
    const _states = allBoardStates;

    return {
        // ── Reactive board snapshot (the only thing renderStones reads) ──
        board: _states[0] ? [..._states[0]] : [],
        lastMoveIdx: null,

        // ── Navigation ────────────────────────────────────
        gameId,
        moves,
        boardSize,
        komi,
        moveIndex: 0,

        // ── Annotation management ─────────────────────────
        annotations: annotations || [],
        activeAnnotationId: null,

        // ── Edit mode state ───────────────────────────────
        editMode: false,
        editDirty: false,
        editTitle: '',
        editSgfText: '',
        editComments: {},           // { moveIndex: string }
        editMoveAnnotations: {},    // { moveIndex: type_string }

        // ── Auto-play ─────────────────────────────────────
        autoPlaying: false,
        autoPlaySpeed: 1000,   // ms per move
        _autoPlayTimer: null,

        // ── UI toggles ────────────────────────────────────
        showAnnotationList: false,
        showSgfRaw: false,
        saving: false,
        deleting: false,
        errorMsg: '',
        successMsg: '',

        // ── Computed ──────────────────────────────────────
        get totalMoves() {
            return this.moves.length;
        },
        get currentMoveInfo() {
            if (this.moveIndex === 0) return null;
            return this.moves[this.moveIndex - 1] ?? null;
        },
        get currentComment() {
            return this.editComments[this.moveIndex] ?? '';
        },
        get currentMoveAnnotation() {
            return this.editMoveAnnotations[this.moveIndex] ?? '';
        },
        get isOwnerOfActive() {
            if (!this.activeAnnotationId) return false;
            const ann = this.annotations.find(a => a.id === this.activeAnnotationId);
            return ann && ann.user_id === currentUserId;
        },

        // ── Navigation ────────────────────────────────────
        goToMove(n) {
            const target = Math.max(0, Math.min(n, this.totalMoves));
            this.moveIndex = target;

            // Assign new array so Alpine always detects the change
            const snapshot = _states[target] ?? _states[this.totalMoves] ?? [];
            this.board = [...snapshot];

            if (target > 0) {
                const mv = this.moves[target - 1];
                if (mv?.coordinate) {
                    const [row, col] = this._coordToRowCol(mv.coordinate);
                    this.lastMoveIdx = row * this.boardSize + col;
                } else {
                    this.lastMoveIdx = null;
                }
            } else {
                this.lastMoveIdx = null;
            }

            // Explicit redraw as fallback in case x-effect hasn't triggered yet
            this.$nextTick(() => this.renderStones());
        },

        goFirst() { this.goToMove(0); },
        goPrev()  { this.goToMove(this.moveIndex - 1); },
        goNext()  { this.goToMove(this.moveIndex + 1); },
        goLast()  { this.goToMove(this.totalMoves); },

        handleMoveListClick(moveNumber) {
            this.goToMove(moveNumber);
        },

        // ── Edit mode ─────────────────────────────────────
        enterEditMode() {
            this.editMode = true;
            this.editDirty = false;
            this.showAnnotationList = false;
            if (this.activeAnnotationId && this.isOwnerOfActive) {
                const ann = this.annotations.find(a => a.id === this.activeAnnotationId);
                this.editTitle = ann ? ann.title : '';
                this._parseSgfAnnotations(ann?.sgf ?? '');
            } else {
                this.editTitle = '';
                this.editComments = {};
                this.editMoveAnnotations = {};
            }
            this._syncSgfText();
        },

        discardEdit() {
            this.editMode = false;
            this.editDirty = false;
            this.editTitle = '';
            this.editComments = {};
            this.editMoveAnnotations = {};
            this.editSgfText = '';
            this.showSgfRaw = false;
            this.errorMsg = '';
        },

        // ── Comment / annotation tools ────────────────────
        setComment(text) {
            if (text.trim() === '') {
                delete this.editComments[this.moveIndex];
            } else {
                this.editComments[this.moveIndex] = text;
            }
            this.editDirty = true;
            this._syncSgfText();
        },

        toggleMoveAnnotation(type) {
            if (this.editMoveAnnotations[this.moveIndex] === type) {
                delete this.editMoveAnnotations[this.moveIndex];
            } else {
                this.editMoveAnnotations[this.moveIndex] = type;
            }
            this.editDirty = true;
            this._syncSgfText();
        },

        // ── Annotation CRUD ───────────────────────────────
        async saveAnnotation() {
            if (!this.editTitle.trim()) {
                this.errorMsg = 'กรุณาใส่ชื่อ annotation';
                return;
            }
            this.saving = true;
            this.errorMsg = '';
            const sgf = this.showSgfRaw ? this.editSgfText : this._buildSgf();
            try {
                if (this.activeAnnotationId && this.isOwnerOfActive) {
                    await window.axios.put(`/games/${this.gameId}/annotations/${this.activeAnnotationId}`, {
                        title: this.editTitle,
                        sgf_content: sgf,
                    });
                    const idx = this.annotations.findIndex(a => a.id === this.activeAnnotationId);
                    if (idx !== -1) {
                        this.annotations[idx].title = this.editTitle;
                        this.annotations[idx].sgf = sgf;
                    }
                    this.successMsg = 'บันทึกแล้ว';
                } else {
                    const { data } = await window.axios.post(`/games/${this.gameId}/annotations`, {
                        title: this.editTitle,
                        sgf_content: sgf,
                    });
                    this.annotations.push({
                        id: data.id,
                        title: data.title,
                        user: data.user,
                        user_id: currentUserId,
                        sgf,
                        created_at: data.created_at,
                    });
                    this.activeAnnotationId = data.id;
                    this.successMsg = 'บันทึก annotation ใหม่แล้ว';
                }
                this.editMode = false;
                this.editDirty = false;
                setTimeout(() => { this.successMsg = ''; }, 3000);
            } catch (err) {
                this.errorMsg = err.response?.data?.error || 'เกิดข้อผิดพลาด';
            } finally {
                this.saving = false;
            }
        },

        async deleteAnnotation(id) {
            if (!window.confirm('ลบ annotation นี้?')) return;
            this.deleting = true;
            try {
                await window.axios.delete(`/games/${this.gameId}/annotations/${id}`);
                this.annotations = this.annotations.filter(a => a.id !== id);
                if (this.activeAnnotationId === id) {
                    this.activeAnnotationId = null;
                    this.editComments = {};
                    this.editMoveAnnotations = {};
                }
            } catch (err) {
                this.errorMsg = err.response?.data?.error || 'เกิดข้อผิดพลาดขณะลบ';
            } finally {
                this.deleting = false;
            }
        },

        loadAnnotation(id) {
            const ann = this.annotations.find(a => a.id === id);
            if (!ann) return;
            this.activeAnnotationId = id;
            this._parseSgfAnnotations(ann.sgf);
            this.showAnnotationList = false;
            this.goToMove(this.moveIndex); // refresh markers without jumping
        },

        clearAnnotation() {
            this.activeAnnotationId = null;
            this.editComments = {};
            this.editMoveAnnotations = {};
            this.showAnnotationList = false;
        },

        // ── SGF helpers ───────────────────────────────────
        _buildSgf() {
            const MOVE_ANN_MAP = {
                good_black:  'GB[1]',
                good_white:  'GW[1]',
                bad:         'BM[1]',
                tesuji:      'TE[1]',
                interesting: 'IT[]',
                doubtful:    'DO[]',
            };

            let sgf = `(;FF[4]GM[1]SZ[${this.boardSize}]KM[${this.komi}]`;
            if (this.editComments[0]) {
                sgf += `C[${this._escapeSgf(this.editComments[0])}]`;
            }
            for (let i = 0; i < this.moves.length; i++) {
                const mv = this.moves[i];
                const color = mv.color === 'black' ? 'B' : 'W';
                const coord = mv.coordinate ? this._coordToSgf(mv.coordinate) : '';
                sgf += `;${color}[${coord}]`;
                const ann = this.editMoveAnnotations[i + 1];
                if (ann && MOVE_ANN_MAP[ann]) sgf += MOVE_ANN_MAP[ann];
                const comment = this.editComments[i + 1];
                if (comment) sgf += `C[${this._escapeSgf(comment)}]`;
            }
            sgf += ')';
            return sgf;
        },

        _syncSgfText() {
            this.editSgfText = this._buildSgf();
        },

        _parseSgfAnnotations(sgf) {
            this.editComments = {};
            this.editMoveAnnotations = {};
            if (!sgf) return;
            const ANN_REVERSE = {
                'GB': 'good_black', 'GW': 'good_white',
                'BM': 'bad', 'TE': 'tesuji',
                'IT': 'interesting', 'DO': 'doubtful',
            };
            const nodes = sgf.split(';').slice(1);
            let moveIdx = 0;
            for (const node of nodes) {
                const cMatch = node.match(/C\[((?:[^\]\\]|\\[\s\S])*)\]/);
                if (cMatch) {
                    this.editComments[moveIdx] = cMatch[1].replace(/\\(.)/g, '$1');
                }
                for (const [tag, type] of Object.entries(ANN_REVERSE)) {
                    if (new RegExp(tag + '\\[').test(node)) {
                        if (moveIdx > 0) this.editMoveAnnotations[moveIdx] = type;
                    }
                }
                if (/[BW]\[/.test(node)) moveIdx++;
            }
        },

        _escapeSgf(text) {
            return text.replace(/\\/g, '\\\\').replace(/]/g, '\\]');
        },

        _coordToSgf(coord) {
            const COL = 'ABCDEFGHJKLMNOPQRST';
            const col = COL.indexOf(coord[0].toUpperCase());
            const row = this.boardSize - parseInt(coord.slice(1));
            return String.fromCharCode(97 + col) + String.fromCharCode(97 + row);
        },

        _coordToRowCol(coord) {
            const COL = 'ABCDEFGHJKLMNOPQRST';
            const col = COL.indexOf(coord[0].toUpperCase());
            const row = this.boardSize - parseInt(coord.slice(1));
            return [row, col];
        },

        // ── Stone rendering ───────────────────────────────
        // Called by x-effect on <g x-ref="stonesLayer">. Reads this.board and
        // this.lastMoveIdx — both are reactive, so Alpine re-runs this whenever
        // goToMove() mutates them.
        renderStones() {
            const layer = this.$refs.stonesLayer;
            if (!layer) return;
            while (layer.firstChild) layer.removeChild(layer.firstChild);
            const ns = 'http://www.w3.org/2000/svg';
            const C = 36;

            // Read reactive properties directly (Alpine tracks these)
            const board = this.board;
            const lastIdx = this.lastMoveIdx;
            const ann = this.editMoveAnnotations[this.moveIndex] ?? '';

            board.forEach((val, idx) => {
                if (val === 0) return;
                const cx = (idx % this.boardSize + 1) * C;
                const cy = (Math.floor(idx / this.boardSize) + 1) * C;
                const g = document.createElementNS(ns, 'g');
                g.setAttribute('style', 'pointer-events:none');

                const shadow = document.createElementNS(ns, 'circle');
                shadow.setAttribute('cx', cx + 1.5);
                shadow.setAttribute('cy', cy + 2);
                shadow.setAttribute('r', C * 0.44);
                shadow.setAttribute('fill', 'rgba(0,0,0,0.25)');
                g.appendChild(shadow);

                const stone = document.createElementNS(ns, 'circle');
                stone.setAttribute('cx', cx);
                stone.setAttribute('cy', cy);
                stone.setAttribute('r', C * 0.44);
                stone.setAttribute('fill', val === 1 ? '#1C1C1C' : '#F0EAD0');
                stone.setAttribute('stroke', val === 1 ? '#000' : '#B8A880');
                stone.setAttribute('stroke-width', '1.5');
                g.appendChild(stone);

                if (val === 1) {
                    const shine = document.createElementNS(ns, 'circle');
                    shine.setAttribute('cx', cx - C * 0.12);
                    shine.setAttribute('cy', cy - C * 0.12);
                    shine.setAttribute('r', C * 0.11);
                    shine.setAttribute('fill', 'rgba(255,255,255,0.35)');
                    g.appendChild(shine);
                }

                if (lastIdx === idx) {
                    const marker = document.createElementNS(ns, 'circle');
                    marker.setAttribute('cx', cx);
                    marker.setAttribute('cy', cy);
                    marker.setAttribute('r', C * 0.2);
                    marker.setAttribute('fill', val === 1 ? '#fff' : '#555');
                    g.appendChild(marker);
                }

                layer.appendChild(g);
            });

            // Annotation ring on last move
            if (ann && lastIdx !== null) {
                const colors = {
                    good_black: '#22C55E', good_white: '#22C55E',
                    tesuji: '#F59E0B', bad: '#EF4444',
                    interesting: '#3B82F6', doubtful: '#A855F7',
                };
                const c = colors[ann];
                if (c) {
                    const cx = (lastIdx % this.boardSize + 1) * C;
                    const cy = (Math.floor(lastIdx / this.boardSize) + 1) * C;
                    const ring = document.createElementNS(ns, 'circle');
                    ring.setAttribute('cx', cx);
                    ring.setAttribute('cy', cy);
                    ring.setAttribute('r', C * 0.48);
                    ring.setAttribute('fill', 'none');
                    ring.setAttribute('stroke', c);
                    ring.setAttribute('stroke-width', '2.5');
                    ring.setAttribute('opacity', '0.9');
                    ring.setAttribute('style', 'pointer-events:none');
                    layer.appendChild(ring);

                    const labels = { good_black: '!', good_white: '!', tesuji: '!', bad: '?', interesting: '!?', doubtful: '?' };
                    const lbl = labels[ann];
                    if (lbl) {
                        const txt = document.createElementNS(ns, 'text');
                        txt.setAttribute('x', cx + C * 0.55);
                        txt.setAttribute('y', cy - C * 0.35);
                        txt.setAttribute('font-size', C * 0.45);
                        txt.setAttribute('font-weight', '900');
                        txt.setAttribute('fill', c);
                        txt.setAttribute('text-anchor', 'middle');
                        txt.setAttribute('style', 'pointer-events:none');
                        txt.textContent = lbl;
                        layer.appendChild(txt);
                    }
                }
            }
        },
    };
}
