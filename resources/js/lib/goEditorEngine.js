const LETTERS = 'ABCDEFGHJKLMNOPQRST';

const HANDICAP_POINTS = {
    19: {
        2: [[3, 15], [15, 3]],
        3: [[3, 15], [15, 3], [15, 15]],
        4: [[3, 3], [3, 15], [15, 3], [15, 15]],
        5: [[3, 3], [3, 15], [15, 3], [15, 15], [9, 9]],
        6: [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9]],
        7: [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9], [9, 9]],
        8: [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9], [9, 3], [9, 15]],
        9: [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9], [9, 3], [9, 15], [9, 9]],
    },
    13: {
        2: [[3, 9], [9, 3]],
        3: [[3, 9], [9, 3], [9, 9]],
        4: [[3, 3], [3, 9], [9, 3], [9, 9]],
        5: [[3, 3], [3, 9], [9, 3], [9, 9], [6, 6]],
    },
    9: {
        2: [[2, 6], [6, 2]],
        3: [[2, 6], [6, 2], [6, 6]],
        4: [[2, 2], [2, 6], [6, 2], [6, 6]],
    },
};

export function createInitialBoard(size, handicap = 0) {
    const board = Array(size * size).fill(0);
    const points = HANDICAP_POINTS[size]?.[handicap] ?? [];

    points.forEach(([row, col]) => {
        board[row * size + col] = 1;
    });

    return board;
}

export function rowColToCoord(row, col, size) {
    return `${LETTERS[col]}${size - row}`;
}

export function coordToRowCol(coord, size) {
    if (!coord) {
        return [0, 0];
    }

    const upper = coord.toUpperCase();
    const col = LETTERS.indexOf(upper[0]);
    const row = size - parseInt(upper.slice(1), 10);

    return [row, col];
}

export function nextColorFrom(color) {
    return color === 'black' ? 'white' : 'black';
}

export function colorToValue(color) {
    return color === 'black' ? 1 : 2;
}

export function valueToColor(value) {
    return value === 1 ? 'black' : 'white';
}

export function buildBaseTimeline(size, handicap, moves) {
    const timeline = {};
    let board = createInitialBoard(size, handicap);
    let koPoint = null;

    timeline['base-0'] = {
        key: 'base-0',
        parentKey: null,
        moveNumber: 0,
        board: [...board],
        koPoint: null,
        nextColor: handicap >= 2 ? 'white' : 'black',
        lastMove: null,
        source: 'base',
    };

    for (const move of moves) {
        const currentColor = move.color ?? timeline[`base-${move.move_number - 1}`]?.nextColor ?? 'black';

        if (move.coordinate) {
            try {
                const [row, col] = coordToRowCol(move.coordinate, size);
                const result = placeStone(board, size, row, col, currentColor, koPoint);
                board = result.board;
                koPoint = result.koPoint;
            } catch (error) {
                koPoint = null;
            }
        } else {
            koPoint = null;
        }

        timeline[`base-${move.move_number}`] = {
            key: `base-${move.move_number}`,
            parentKey: `base-${Math.max(0, move.move_number - 1)}`,
            moveNumber: move.move_number,
            board: [...board],
            koPoint,
            nextColor: nextColorFrom(currentColor),
            lastMove: {
                move_number: move.move_number,
                color: currentColor,
                coordinate: move.coordinate ?? null,
            },
            source: 'base',
        };
    }

    return timeline;
}

export function placeStone(sourceBoard, size, row, col, color, koPoint = null) {
    if (row < 0 || row >= size || col < 0 || col >= size) {
        throw new Error('ตำแหน่งอยู่นอกกระดาน');
    }

    const board = [...sourceBoard];
    const idx = row * size + col;

    if (board[idx] !== 0) {
        throw new Error('ตำแหน่งนี้มีหมากอยู่แล้ว');
    }

    const coordinate = rowColToCoord(row, col, size);
    if (koPoint && coordinate === koPoint) {
        throw new Error('ห้ามเล่นโคซ้ำทันที');
    }

    const colorValue = colorToValue(color);
    const opponentValue = colorValue === 1 ? 2 : 1;
    board[idx] = colorValue;

    const captured = [];
    getNeighbors(idx, size).forEach((neighborIdx) => {
        if (board[neighborIdx] !== opponentValue) {
            return;
        }

        const group = getGroup(board, size, neighborIdx);
        if (group.liberties.length === 0) {
            group.stones.forEach((stoneIdx) => {
                const stoneRow = Math.floor(stoneIdx / size);
                const stoneCol = stoneIdx % size;
                captured.push([stoneRow, stoneCol]);
                board[stoneIdx] = 0;
            });
        }
    });

    const ownGroup = getGroup(board, size, idx);
    if (ownGroup.liberties.length === 0) {
        throw new Error('ห้ามเดินฆ่าตัวเอง');
    }

    let newKoPoint = null;
    if (captured.length === 1 && ownGroup.stones.length === 1 && ownGroup.liberties.length === 1) {
        newKoPoint = rowColToCoord(captured[0][0], captured[0][1], size);
    }

    return {
        board,
        koPoint: newKoPoint,
        captured,
    };
}

function getGroup(board, size, startIdx) {
    const color = board[startIdx];

    if (color === 0) {
        return { stones: [], liberties: [] };
    }

    const visited = new Set();
    const liberties = new Set();
    const stones = [];
    const stack = [startIdx];

    while (stack.length > 0) {
        const idx = stack.pop();
        if (visited.has(idx)) {
            continue;
        }

        visited.add(idx);

        if (board[idx] !== color) {
            continue;
        }

        stones.push(idx);

        getNeighbors(idx, size).forEach((neighborIdx) => {
            if (board[neighborIdx] === 0) {
                liberties.add(neighborIdx);
                return;
            }

            if (board[neighborIdx] === color && !visited.has(neighborIdx)) {
                stack.push(neighborIdx);
            }
        });
    }

    return {
        stones,
        liberties: Array.from(liberties),
    };
}

function getNeighbors(idx, size) {
    const row = Math.floor(idx / size);
    const col = idx % size;
    const neighbors = [];

    if (row > 0) {
        neighbors.push((row - 1) * size + col);
    }

    if (row < size - 1) {
        neighbors.push((row + 1) * size + col);
    }

    if (col > 0) {
        neighbors.push(row * size + (col - 1));
    }

    if (col < size - 1) {
        neighbors.push(row * size + (col + 1));
    }

    return neighbors;
}
