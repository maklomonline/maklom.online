import { buildBaseTimeline, coordToRowCol, nextColorFrom, placeStone, rowColToCoord } from '../lib/goEditorEngine';

export function gameAnnotationEditor(config) {
    const baseTimeline = buildBaseTimeline(config.boardSize, config.handicap ?? 0, config.moves ?? []);

    return {
        gameId: config.gameId,
        boardSize: config.boardSize,
        komi: config.komi ?? 0,
        handicap: config.handicap ?? 0,
        moves: Array.isArray(config.moves) ? config.moves : [],
        canEdit: !!config.canEdit,
        annotationMeta: config.annotationMeta ?? null,
        title: config.annotationMeta?.title ?? '',
        payload: normalizePayload(config.annotationPayload),
        baseTimeline,
        board: [...(baseTimeline['base-0']?.board ?? Array(config.boardSize * config.boardSize).fill(0))],
        currentKey: 'base-0',
        lastMoveIdx: null,
        toolMode: 'navigate',
        saveState: 'idle',
        errorMsg: '',
        successMsg: '',
        branchSeed: 0,
        stateCache: {},

        get totalMoves() {
            return this.moves.length;
        },

        get currentState() {
            return this.getStateForKey(this.currentKey) || this.baseTimeline['base-0'];
        },

        get currentPosition() {
            return this.ensurePosition(this.currentKey);
        },

        get currentComment() {
            return this.currentPosition.comment ?? '';
        },

        get currentMarks() {
            return this.currentPosition.marks ?? [];
        },

        get currentChildren() {
            const pos = this.ensurePosition(this.currentKey);
            return [...(pos.children ?? [])]
                .map((childKey) => ({ key: childKey, node: this.payload.positions[childKey] }))
                .filter(({ node }) => !!node)
                .sort((left, right) => (left.node.order ?? 0) - (right.node.order ?? 0));
        },

        get branchOptions() {
            const options = [];
            const state = this.currentState;
            if (!state) return [];

            if (this.currentKey.startsWith('base-')) {
                const nextBaseKey = `base-${state.moveNumber + 1}`;
                if (this.baseTimeline[nextBaseKey]) {
                    options.push({
                        key: nextBaseKey,
                        label: `เมนไลน์ · ตา ${state.moveNumber + 1}`,
                    });
                }
            }

            this.currentChildren.forEach(({ key, node }, index) => {
                const label = node.is_pass
                    ? `สาขา ${index + 1} · ผ่าน`
                    : `สาขา ${index + 1} · ${node.color === 'black' ? '⚫' : '⚪'} ${node.coordinate}`;

                options.push({ key, label });
            });

            return options;
        },

        get currentMoveLabel() {
            const state = this.currentState;
            if (!state || !state.lastMove) return 'ตำแหน่งเริ่มต้น';
            return `${state.lastMove.color === 'black' ? '⚫' : '⚪'} ตาที่ ${state.moveNumber}: ${state.lastMove.coordinate ?? 'ผ่าน'}`;
        },

        get currentPath() {
            const segments = [];
            let pointer = this.currentKey;
            while (pointer) {
                const state = this.getStateForKey(pointer);
                if (!state) break;
                segments.unshift({
                    key: pointer,
                    label: state.moveNumber === 0 ? 'เริ่มต้น' : `${state.moveNumber}${pointer.startsWith('base-') ? '' : '*'}`
                });
                pointer = state.parentKey;
            }
            return segments;
        },

        get treeNodes() {
            const list = [];
            const visited = new Set();

            const traverse = (key, depth) => {
                if (!key || visited.has(key)) return;
                visited.add(key);

                const state = this.getStateForKey(key);
                if (!state) return;

                let label = '';
                if (state.moveNumber === 0) {
                    label = 'เริ่มต้น';
                } else {
                    const colorLabel = state.lastMove?.color === 'black' ? '⚫' : '⚪';
                    const coordLabel = state.lastMove?.coordinate ?? 'ผ่าน';
                    label = `${state.moveNumber}. ${colorLabel} ${coordLabel}`;
                }

                const pos = this.payload.positions[key];
                const hasComment = !!pos?.comment;
                const hasMarks = !!(pos?.marks && pos.marks.length > 0);

                list.push({
                    key,
                    depth,
                    label,
                    hasComment,
                    hasMarks,
                    isMainline: key.startsWith('base-'),
                });

                const children = [];
                if (key.startsWith('base-')) {
                    const nextBaseKey = `base-${state.moveNumber + 1}`;
                    if (this.baseTimeline[nextBaseKey]) {
                        children.push(nextBaseKey);
                    }
                }

                if (pos && Array.isArray(pos.children)) {
                    const sortedVars = [...pos.children]
                        .filter(k => this.payload.positions[k])
                        .sort((a, b) => (this.payload.positions[a].order ?? 0) - (this.payload.positions[b].order ?? 0));
                    children.push(...sortedVars);
                }

                children.forEach((childKey, idx) => {
                    traverse(childKey, depth + (idx > 0 ? 1 : 0));
                });
            };

            traverse('base-0', 0);
            return list;
        },

        init() {
            const initialKey = this.payload.last_position_key;
            this.currentKey = this.getStateForKey(initialKey) ? initialKey : 'base-0';
            this.$nextTick(() => {
                this.refreshBoard();
            });
        },

        ensurePosition(key) {
            if (!this.payload.positions[key]) {
                this.payload.positions[key] = {
                    comment: '',
                    marks: [],
                    children: [],
                };
            }
            return this.payload.positions[key];
        },

        getStateForKey(key) {
            if (!key) return null;
            if (this.stateCache[key]) return this.stateCache[key];

            if (this.baseTimeline[key]) {
                const state = { ...this.baseTimeline[key] };
                this.stateCache[key] = state;
                return state;
            }

            const node = this.payload.positions[key];
            if (!node) return null;

            const parentState = this.getStateForKey(node.parent);
            if (!parentState) return null;

            let board = [...parentState.board];
            let koPoint = null;

            if (!node.is_pass && node.coordinate) {
                try {
                    const [row, col] = coordToRowCol(node.coordinate, this.boardSize);
                    const result = placeStone(parentState.board, this.boardSize, row, col, node.color, parentState.koPoint);
                    board = result.board;
                    koPoint = result.koPoint;
                } catch (e) {
                    board = [...parentState.board];
                }
            }

            const state = {
                key,
                parentKey: node.parent,
                moveNumber: parentState.moveNumber + 1,
                board,
                koPoint,
                nextColor: nextColorFrom(node.color),
                lastMove: {
                    move_number: parentState.moveNumber + 1,
                    color: node.color,
                    coordinate: node.is_pass ? null : node.coordinate,
                },
                source: 'branch',
            };
            this.stateCache[key] = state;
            return state;
        },

        refreshBoard() {
            const state = this.currentState || this.baseTimeline['base-0'];
            this.board = [...state.board];
            this.lastMoveIdx = state.lastMove?.coordinate
                ? (() => {
                    const [row, col] = coordToRowCol(state.lastMove.coordinate, this.boardSize);
                    return row * this.boardSize + col;
                })()
                : null;
            this.payload.last_position_key = this.currentKey;
            this.$nextTick(() => this.renderBoard());
        },

        goFirst() { this.currentKey = 'base-0'; this.refreshBoard(); },
        goPrev() { if (this.currentState.parentKey) { this.currentKey = this.currentState.parentKey; this.refreshBoard(); } },
        goNext() { const next = this.getNextKey(this.currentKey); if (next) { this.currentKey = next; this.refreshBoard(); } },
        goLast() { let p = this.currentKey, n; while ((n = this.getNextKey(p))) p = n; this.currentKey = p; this.refreshBoard(); },

        getNextKey(fromKey) {
            const state = this.getStateForKey(fromKey);
            if (!state) return null;
            if (fromKey.startsWith('base-')) {
                const nextBase = `base-${state.moveNumber + 1}`;
                if (this.baseTimeline[nextBase]) return nextBase;
            }
            const children = this.currentChildren;
            return children.length > 0 ? children[0].key : null;
        },

        goToBaseMove(moveNumber) {
            const key = `base-${moveNumber}`;
            if (this.baseTimeline[key]) { this.currentKey = key; this.refreshBoard(); }
        },

        selectBranch(key) {
            if (this.getStateForKey(key)) { this.currentKey = key; this.refreshBoard(); }
        },

        setTool(tool) { if (this.canEdit) this.toolMode = tool; },

        updateComment(value) {
            if (!this.canEdit) return;
            this.ensurePosition(this.currentKey).comment = value;
        },

        handleBoardClick(event) {
            if (!this.canEdit) return;
            const { row, col } = this.svgCoords(event);
            if (row < 0 || row >= this.boardSize || col < 0 || col >= this.boardSize) return;
            const coordinate = rowColToCoord(row, col, this.boardSize);

            if (this.toolMode === 'move') {
                this.addMoveBranch(coordinate, row, col);
            } else if (['triangle', 'square', 'circle', 'label', 'number', 'erase'].includes(this.toolMode)) {
                this.applyMarkTool(coordinate);
            }
        },

        addMoveBranch(coordinate, row, col) {
            const state = this.currentState;
            if (state.board[row * this.boardSize + col] !== 0) {
                this.errorMsg = 'ตำแหน่งนี้มีหมากอยู่แล้ว';
                return;
            }
            try {
                placeStone(state.board, this.boardSize, row, col, state.nextColor, state.koPoint);
                this.errorMsg = '';
                this.createOrSelectChild({ coordinate, is_pass: false, color: state.nextColor });
            } catch (e) {
                this.errorMsg = e.message;
            }
        },

        addPassBranch() {
            if (!this.canEdit) return;
            this.createOrSelectChild({ coordinate: null, is_pass: true, color: this.currentState.nextColor });
        },

        createOrSelectChild(data) {
            const pos = this.ensurePosition(this.currentKey);
            const state = this.currentState;

            if (this.currentKey.startsWith('base-')) {
                const nextBaseKey = `base-${state.moveNumber + 1}`;
                const nextBaseState = this.baseTimeline[nextBaseKey];
                if (nextBaseState && nextBaseState.lastMove) {
                    const lm = nextBaseState.lastMove;
                    const lmIsPass = !lm.coordinate;
                    if (lm.color === data.color && (lm.coordinate || null) === (data.coordinate || null) && lmIsPass === !!data.is_pass) {
                        this.currentKey = nextBaseKey;
                        this.refreshBoard();
                        return;
                    }
                }
            }

            const existing = pos.children.find(k => {
                const c = this.payload.positions[k];
                return c && c.color === data.color && c.coordinate === data.coordinate && !!c.is_pass === !!data.is_pass;
            });

            if (existing) {
                this.currentKey = existing;
            } else {
                const newKey = `node-${Date.now()}-${++this.branchSeed}`;
                this.payload.positions[newKey] = {
                    parent: this.currentKey,
                    color: data.color,
                    coordinate: data.coordinate,
                    is_pass: !!data.is_pass,
                    comment: '',
                    marks: [],
                    children: [],
                    order: pos.children.length,
                };
                pos.children.push(newKey);
                this.currentKey = newKey;
            }
            this.refreshBoard();
        },

        applyMarkTool(coord) {
            const pos = this.ensurePosition(this.currentKey);
            if (this.toolMode === 'erase') {
                pos.marks = pos.marks.filter(m => m.coordinate !== coord);
            } else if (['triangle', 'square', 'circle'].includes(this.toolMode)) {
                const has = pos.marks.find(m => m.coordinate === coord && m.type === this.toolMode);
                if (has) {
                    pos.marks = pos.marks.filter(m => !(m.coordinate === coord && m.type === this.toolMode));
                } else {
                    pos.marks = [...pos.marks.filter(m => m.coordinate !== coord || ['label', 'number'].includes(m.type)), { type: this.toolMode, coordinate: coord }];
                }
            } else if (this.toolMode === 'label') {
                const text = nextAlphabetLabel(pos.marks.filter(m => m.type === 'label').map(m => m.text));
                this.upsertTextMark(coord, 'label', text);
            } else if (this.toolMode === 'number') {
                const text = String(nextNumberLabel(pos.marks.filter(m => m.type === 'number').map(m => m.text)));
                this.upsertTextMark(coord, 'number', text);
            }
            this.renderBoard();
        },

        upsertTextMark(coord, type, text) {
            const pos = this.ensurePosition(this.currentKey);
            pos.marks = [...pos.marks.filter(m => !(m.coordinate === coord && (m.type === 'label' || m.type === 'number'))), { type, coordinate: coord, text }];
            this.renderBoard();
        },

        async saveAnnotation() {
            if (!this.canEdit || !this.title.trim()) { this.errorMsg = 'กรุณาใส่ชื่อ'; return; }
            this.saveState = 'saving'; this.errorMsg = '';
            try {
                const requestData = { title: this.title.trim(), payload: this.exportPayload() };
                if (this.annotationMeta?.id) {
                    const { data } = await window.axios.put(`/games/${this.gameId}/annotation/${this.annotationMeta.id}`, requestData);
                    this.annotationMeta.updated_at = data.updated_at;
                } else {
                    const { data } = await window.axios.post(`/games/${this.gameId}/annotation`, requestData);
                    this.annotationMeta = data;
                    window.history.replaceState({}, '', data.view_url);
                }
                this.successMsg = 'บันทึกเรียบร้อย';
            } catch (e) {
                this.errorMsg = e.response?.data?.message || 'บันทึกผิดพลาด';
            } finally { this.saveState = 'idle'; }
        },

        exportPayload() {
            const positions = {};
            Object.entries(this.payload.positions).forEach(([k, v]) => {
                if (!k.startsWith('base-') && !v.parent) return;
                positions[k] = { ...v, children: v.children.filter(ck => this.payload.positions[ck]) };
            });
            return { version: 2, last_position_key: this.currentKey, positions };
        },

        svgCoords(event) {
            const svg = event.currentTarget.querySelector('svg');
            const rect = svg.getBoundingClientRect();
            const viewBoxSize = (this.boardSize + 1) * 36;
            const scale = rect.width / viewBoxSize;
            return {
                col: Math.round(((event.clientX - rect.left) / scale) / 36) - 1,
                row: Math.round(((event.clientY - rect.top) / scale) / 36) - 1,
            };
        },

        renderBoard() {
            const layer = this.$refs.stonesLayer;
            if (!layer) return;
            layer.innerHTML = '';
            const ns = 'http://www.w3.org/2000/svg', cell = 36;

            this.board.forEach((val, idx) => {
                if (val === 0) return;
                const cx = (idx % this.boardSize + 1) * cell, cy = (Math.floor(idx / this.boardSize) + 1) * cell;
                const g = document.createElementNS(ns, 'g');
                const s = document.createElementNS(ns, 'circle');
                s.setAttribute('cx', cx); s.setAttribute('cy', cy); s.setAttribute('r', cell * 0.44);
                s.setAttribute('fill', val === 1 ? '#1C1C1C' : '#F0EAD0');
                s.setAttribute('stroke', val === 1 ? '#000' : '#B8A880');
                g.appendChild(s);
                layer.appendChild(g);
            });

            if (this.lastMoveIdx !== null) {
                const cx = (this.lastMoveIdx % this.boardSize + 1) * cell, cy = (Math.floor(this.lastMoveIdx / this.boardSize) + 1) * cell;
                const m = document.createElementNS(ns, 'circle');
                m.setAttribute('cx', cx); m.setAttribute('cy', cy); m.setAttribute('r', cell * 0.15);
                m.setAttribute('fill', this.board[this.lastMoveIdx] === 1 ? '#fff' : '#000');
                layer.appendChild(m);
            }

            this.currentMarks.forEach(m => {
                const [r, c] = coordToRowCol(m.coordinate, this.boardSize);
                const cx = (c + 1) * cell, cy = (r + 1) * cell;
                const color = this.board[r * this.boardSize + c] === 1 ? '#fff' : '#000';
                if (['triangle', 'square', 'circle'].includes(m.type)) {
                    const el = document.createElementNS(ns, m.type === 'triangle' ? 'polygon' : (m.type === 'square' ? 'rect' : 'circle'));
                    if (m.type === 'triangle') el.setAttribute('points', `${cx},${cy - 8} ${cx - 8},${cy + 6} ${cx + 8},${cy + 6}`);
                    else if (m.type === 'square') { el.setAttribute('x', cx - 7); el.setAttribute('y', cy - 7); el.setAttribute('width', 14); el.setAttribute('height', 14); }
                    else { el.setAttribute('cx', cx); el.setAttribute('cy', cy); el.setAttribute('r', 8); }
                    el.setAttribute('fill', 'none'); el.setAttribute('stroke', color); el.setAttribute('stroke-width', '2');
                    layer.appendChild(el);
                } else {
                    const t = document.createElementNS(ns, 'text');
                    t.setAttribute('x', cx); t.setAttribute('y', cy + 4); t.setAttribute('text-anchor', 'middle');
                    t.setAttribute('font-size', '12'); t.setAttribute('font-weight', 'bold'); t.setAttribute('fill', color);
                    t.textContent = m.text; layer.appendChild(t);
                }
            });
        }
    };
}

function normalizePayload(p) {
    const pos = {};
    Object.entries(p?.positions ?? {}).forEach(([k, v]) => {
        pos[k] = { comment: v.comment || '', marks: v.marks || [], children: v.children || [], parent: v.parent, color: v.color, coordinate: v.coordinate, is_pass: !!v.is_pass, order: v.order || 0 };
    });
    return { version: 2, last_position_key: p?.last_position_key || 'base-0', positions: pos };
}

function nextAlphabetLabel(labels) {
    const used = labels.filter(Boolean).map(l => l.toUpperCase());
    let i = 1;
    while (used.includes(indexToLetters(i))) i++;
    return indexToLetters(i);
}

function indexToLetters(i) {
    let out = '';
    while (i > 0) { i--; out = String.fromCharCode(65 + (i % 26)) + out; i = Math.floor(i / 26); }
    return out;
}

function nextNumberLabel(labels) {
    const nums = labels.map(l => parseInt(l, 10)).filter(n => !isNaN(n));
    return (nums.length ? Math.max(...nums) : 0) + 1;
}
