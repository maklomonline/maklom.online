export function gameReplay(config) {
    const boardStates = config.boardStates || [];
    const moves = config.moves || [];
    const boardSize = config.boardSize || 19;
    const annotations = config.annotations || [];

    return {
        gameId: config.gameId,
        boardSize,
        moves,
        moveIndex: 0,
        lastMoveIdx: null,
        board: boardStates[0] ? [...boardStates[0]] : Array(boardSize * boardSize).fill(0),
        annotations,
        currentAnnotation: null,
        deleting: false,

        get totalMoves() {
            return this.moves.length;
        },

        get currentMoveInfo() {
            return this.moveIndex > 0 ? this.moves[this.moveIndex - 1] ?? null : null;
        },

        get currentMoveLabel() {
            if (!this.currentMoveInfo) {
                return 'ตำแหน่งเริ่มต้น';
            }

            return `${this.currentMoveInfo.color === 'black' ? '⚫' : '⚪'} ตาที่ ${this.currentMoveInfo.move_number}: ${this.currentMoveInfo.coordinate ?? 'ผ่าน'}`;
        },

        get currentComment() {
            if (!this.currentAnnotation) return null;
            const posKey = `base-${this.moveIndex}`;
            return this.currentAnnotation.payload?.positions?.[posKey]?.comment || null;
        },

        get currentMarks() {
            if (!this.currentAnnotation) return [];
            const posKey = `base-${this.moveIndex}`;
            return this.currentAnnotation.payload?.positions?.[posKey]?.marks || [];
        },

        init() {
            this.$watch('moveIndex', () => {
                this.updateBoard();
            });

            this.$nextTick(() => {
                this.updateBoard();
            });
        },

        goToMove(target) {
            this.moveIndex = Math.max(0, Math.min(target, this.totalMoves));
        },

        goFirst() {
            this.goToMove(0);
        },

        goPrev() {
            this.goToMove(this.moveIndex - 1);
        },

        goNext() {
            this.goToMove(this.moveIndex + 1);
        },

        goLast() {
            this.goToMove(this.totalMoves);
        },

        selectMove(moveNumber) {
            this.goToMove(moveNumber);
        },

        updateBoard() {
            const snapshot = boardStates[this.moveIndex] ?? boardStates[this.totalMoves] ?? [];
            this.board = [...snapshot];

            if (!this.currentMoveInfo?.coordinate) {
                this.lastMoveIdx = null;
            } else {
                const [row, col] = this.coordToRowCol(this.currentMoveInfo.coordinate);
                this.lastMoveIdx = row * this.boardSize + col;
            }

            this.renderStones();
        },

        coordToRowCol(coord) {
            const letters = 'ABCDEFGHJKLMNOPQRST';
            const col = letters.indexOf(coord[0].toUpperCase());
            const row = this.boardSize - parseInt(coord.slice(1), 10);

            return [row, col];
        },

        loadAnnotation(annotation) {
            if (this.currentAnnotation?.id === annotation.id) {
                this.currentAnnotation = null;
            } else {
                this.currentAnnotation = annotation;
            }
            this.renderStones();
        },

        async deleteAnnotation(id) {
            if (!window.confirm('ยืนยันการลบ annotation นี้?')) return;

            this.deleting = true;
            try {
                await window.axios.delete(`/games/${this.gameId}/annotation/${id}`);
                this.annotations = this.annotations.filter(a => a.id !== id);
                if (this.currentAnnotation?.id === id) {
                    this.currentAnnotation = null;
                    this.renderStones();
                }
            } catch (err) {
                alert('ไม่สามารถลบได้: ' + (err.response?.data?.error || 'เกิดข้อผิดพลาด'));
            } finally {
                this.deleting = false;
            }
        },

        renderStones() {
            const layer = this.$refs.stonesLayer;
            if (!layer) {
                return;
            }

            while (layer.firstChild) {
                layer.removeChild(layer.firstChild);
            }

            const ns = 'http://www.w3.org/2000/svg';
            const cell = 36;

            this.board.forEach((value, idx) => {
                if (value === 0) {
                    return;
                }

                const cx = (idx % this.boardSize + 1) * cell;
                const cy = (Math.floor(idx / this.boardSize) + 1) * cell;
                const group = document.createElementNS(ns, 'g');

                const shadow = document.createElementNS(ns, 'circle');
                shadow.setAttribute('cx', cx + 1.5);
                shadow.setAttribute('cy', cy + 2);
                shadow.setAttribute('r', cell * 0.44);
                shadow.setAttribute('fill', 'rgba(0,0,0,0.25)');
                group.appendChild(shadow);

                const stone = document.createElementNS(ns, 'circle');
                stone.setAttribute('cx', cx);
                stone.setAttribute('cy', cy);
                stone.setAttribute('r', cell * 0.44);
                stone.setAttribute('fill', value === 1 ? '#1C1C1C' : '#F0EAD0');
                stone.setAttribute('stroke', value === 1 ? '#000' : '#B8A880');
                stone.setAttribute('stroke-width', '1.5');
                group.appendChild(stone);

                if (value === 1) {
                    const shine = document.createElementNS(ns, 'circle');
                    shine.setAttribute('cx', cx - cell * 0.12);
                    shine.setAttribute('cy', cy - cell * 0.12);
                    shine.setAttribute('r', cell * 0.11);
                    shine.setAttribute('fill', 'rgba(255,255,255,0.35)');
                    group.appendChild(shine);
                }

                if (idx === this.lastMoveIdx) {
                    const marker = document.createElementNS(ns, 'circle');
                    marker.setAttribute('cx', cx);
                    marker.setAttribute('cy', cy);
                    marker.setAttribute('r', cell * 0.2);
                    marker.setAttribute('fill', value === 1 ? '#fff' : '#555');
                    group.appendChild(marker);
                }

                layer.appendChild(group);
            });

            // Draw marks if any
            this.currentMarks.forEach((mark) => {
                const [row, col] = this.coordToRowCol(mark.coordinate);
                const idx = row * this.boardSize + col;
                const stoneValue = this.board[idx];
                const cx = (col + 1) * cell;
                const cy = (row + 1) * cell;
                const stroke = stoneValue === 1 ? '#fff' : (stoneValue === 2 ? '#111' : '#2563EB');

                if (mark.type === 'triangle') {
                    const triangle = document.createElementNS(ns, 'polygon');
                    triangle.setAttribute('points', `${cx},${cy - 9} ${cx - 9},${cy + 7} ${cx + 9},${cy + 7}`);
                    triangle.setAttribute('fill', 'none');
                    triangle.setAttribute('stroke', stroke);
                    triangle.setAttribute('stroke-width', '2.5');
                    layer.appendChild(triangle);
                } else if (mark.type === 'square') {
                    const square = document.createElementNS(ns, 'rect');
                    square.setAttribute('x', cx - 8); square.setAttribute('y', cy - 8);
                    square.setAttribute('width', 16); square.setAttribute('height', 16);
                    square.setAttribute('fill', 'none'); square.setAttribute('stroke', stroke);
                    square.setAttribute('stroke-width', '2.5');
                    layer.appendChild(square);
                } else if (mark.type === 'circle') {
                    const circle = document.createElementNS(ns, 'circle');
                    circle.setAttribute('cx', cx); circle.setAttribute('cy', cy);
                    circle.setAttribute('r', 9);
                    circle.setAttribute('fill', 'none'); circle.setAttribute('stroke', stroke);
                    circle.setAttribute('stroke-width', '2.5');
                    layer.appendChild(circle);
                } else if (mark.type === 'label' || mark.type === 'number') {
                    const text = document.createElementNS(ns, 'text');
                    text.setAttribute('x', cx); text.setAttribute('y', cy + 4.5);
                    text.setAttribute('text-anchor', 'middle'); text.setAttribute('font-size', '12');
                    text.setAttribute('font-weight', '800'); text.setAttribute('fill', stroke);
                    text.textContent = mark.text ?? '';
                    layer.appendChild(text);
                }
            });
        },
    };
}
