<template>
    <div class="h-full flex flex-col">
        <!-- Toolbar -->
        <div
            class="p-2 border-b border-gray-700 flex items-center space-x-2 flex-wrap"
        >
            <!-- Tools -->
            <div class="flex items-center space-x-1 bg-gray-700 rounded-lg p-1">
                <button
                    v-for="tool in tools"
                    :key="tool.id"
                    @click="currentTool = tool.id"
                    :class="[
                        'p-2 rounded transition-colors',
                        currentTool === tool.id
                            ? 'bg-primary-600 text-white'
                            : 'text-gray-400 hover:text-white',
                    ]"
                    :title="tool.label"
                >
                    <component :is="tool.icon" class="w-4 h-4" />
                </button>
            </div>

            <!-- Colors -->
            <div class="flex items-center space-x-1">
                <button
                    v-for="color in colors"
                    :key="color"
                    @click="currentColor = color"
                    :class="[
                        'w-6 h-6 rounded-full border-2 transition-transform',
                        currentColor === color
                            ? 'border-white scale-110'
                            : 'border-transparent',
                    ]"
                    :style="{ backgroundColor: color }"
                ></button>
            </div>

            <!-- Stroke Width -->
            <select
                v-model.number="strokeWidth"
                class="bg-gray-700 text-white text-sm rounded px-2 py-1 border border-gray-600"
            >
                <option :value="2">Thin</option>
                <option :value="4">Medium</option>
                <option :value="8">Thick</option>
            </select>

            <!-- Clear (Host only) -->
            <button
                v-if="isHost"
                @click="$emit('clear')"
                class="ml-auto px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors"
            >
                Clear All
            </button>
        </div>

        <!-- Canvas -->
        <div class="flex-1 overflow-hidden bg-white relative">
            <canvas
                ref="canvas"
                @mousedown="startDrawing"
                @mousemove="draw"
                @mouseup="stopDrawing"
                @mouseleave="stopDrawing"
                @touchstart.prevent="handleTouchStart"
                @touchmove.prevent="handleTouchMove"
                @touchend.prevent="stopDrawing"
                class="absolute inset-0 cursor-crosshair"
            ></canvas>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, h, nextTick } from "vue";

const props = defineProps({
    meetingId: Number,
    strokes: Array,
    isHost: Boolean,
});

const emit = defineEmits(["draw", "clear"]);

const canvas = ref(null);
let ctx = null;

const currentTool = ref("pen");
const currentColor = ref("#000000");
const strokeWidth = ref(4);
const isDrawing = ref(false);
const currentStroke = ref(null);
const startPoint = ref(null);

const colors = [
    "#000000",
    "#EF4444",
    "#F59E0B",
    "#10B981",
    "#3B82F6",
    "#8B5CF6",
    "#EC4899",
    "#FFFFFF",
];

// Tool icons as render functions
const PenIcon = {
    render: () =>
        h(
            "svg",
            { fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    "stroke-width": "2",
                    d: "M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z",
                }),
            ],
        ),
};

const EraserIcon = {
    render: () =>
        h(
            "svg",
            { fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    "stroke-width": "2",
                    d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16",
                }),
            ],
        ),
};

const LineIcon = {
    render: () =>
        h(
            "svg",
            { fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    "stroke-width": "2",
                    d: "M4 20L20 4",
                }),
            ],
        ),
};

const RectIcon = {
    render: () =>
        h(
            "svg",
            { fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
            [
                h("rect", {
                    x: "4",
                    y: "4",
                    width: "16",
                    height: "16",
                    rx: "2",
                    "stroke-width": "2",
                }),
            ],
        ),
};

const CircleIcon = {
    render: () =>
        h(
            "svg",
            { fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
            [h("circle", { cx: "12", cy: "12", r: "8", "stroke-width": "2" })],
        ),
};

const tools = [
    { id: "pen", label: "Pen", icon: PenIcon },
    { id: "eraser", label: "Eraser", icon: EraserIcon },
    { id: "line", label: "Line", icon: LineIcon },
    { id: "rectangle", label: "Rectangle", icon: RectIcon },
    { id: "circle", label: "Circle", icon: CircleIcon },
];

const initCanvas = () => {
    if (!canvas.value) return;

    const rect = canvas.value.parentElement.getBoundingClientRect();
    canvas.value.width = rect.width;
    canvas.value.height = rect.height;

    ctx = canvas.value.getContext("2d");
    ctx.lineCap = "round";
    ctx.lineJoin = "round";

    redrawCanvas();
};

const redrawCanvas = () => {
    if (!ctx || !canvas.value) return;

    ctx.fillStyle = "#FFFFFF";
    ctx.fillRect(0, 0, canvas.value.width, canvas.value.height);

    props.strokes.forEach((stroke) => drawStroke(stroke));
};

const drawStroke = (stroke) => {
    if (!ctx) return;

    ctx.strokeStyle = stroke.tool === "eraser" ? "#FFFFFF" : stroke.color;
    ctx.lineWidth = stroke.stroke_width;

    const points = stroke.points;
    if (!points || points.length === 0) return;

    switch (stroke.tool) {
        case "pen":
        case "eraser":
            ctx.beginPath();
            ctx.moveTo(points[0][0], points[0][1]);
            for (let i = 1; i < points.length; i++) {
                ctx.lineTo(points[i][0], points[i][1]);
            }
            ctx.stroke();
            break;
        case "line":
            if (points.length >= 2) {
                ctx.beginPath();
                ctx.moveTo(points[0][0], points[0][1]);
                ctx.lineTo(points[1][0], points[1][1]);
                ctx.stroke();
            }
            break;
        case "rectangle":
            if (points.length >= 2) {
                const [x1, y1] = points[0];
                const [x2, y2] = points[1];
                ctx.strokeRect(x1, y1, x2 - x1, y2 - y1);
            }
            break;
        case "circle":
            if (points.length >= 2) {
                const [cx, cy] = points[0];
                const [ex, ey] = points[1];
                const radius = Math.sqrt(
                    Math.pow(ex - cx, 2) + Math.pow(ey - cy, 2),
                );
                ctx.beginPath();
                ctx.arc(cx, cy, radius, 0, Math.PI * 2);
                ctx.stroke();
            }
            break;
    }
};

const getCoordinates = (event) => {
    const rect = canvas.value.getBoundingClientRect();
    const x = (event.clientX || event.touches?.[0]?.clientX) - rect.left;
    const y = (event.clientY || event.touches?.[0]?.clientY) - rect.top;
    return [x, y];
};

const startDrawing = (event) => {
    isDrawing.value = true;
    const [x, y] = getCoordinates(event);
    startPoint.value = [x, y];

    currentStroke.value = {
        stroke_id: crypto.randomUUID(),
        tool: currentTool.value,
        color: currentColor.value,
        stroke_width: strokeWidth.value,
        points: [[x, y]],
    };

    if (currentTool.value === "pen" || currentTool.value === "eraser") {
        ctx.strokeStyle =
            currentTool.value === "eraser" ? "#FFFFFF" : currentColor.value;
        ctx.lineWidth = strokeWidth.value;
        ctx.beginPath();
        ctx.moveTo(x, y);
    }
};

const draw = (event) => {
    if (!isDrawing.value || !currentStroke.value) return;

    const [x, y] = getCoordinates(event);

    if (currentTool.value === "pen" || currentTool.value === "eraser") {
        currentStroke.value.points.push([x, y]);
        ctx.lineTo(x, y);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x, y);
    } else {
        // For shapes, redraw canvas and show preview
        redrawCanvas();

        const tempStroke = {
            ...currentStroke.value,
            points: [startPoint.value, [x, y]],
        };
        drawStroke(tempStroke);
    }
};

const stopDrawing = () => {
    if (!isDrawing.value || !currentStroke.value) return;

    isDrawing.value = false;

    if (currentStroke.value.points.length > 0) {
        if (
            currentTool.value !== "pen" &&
            currentTool.value !== "eraser" &&
            startPoint.value
        ) {
            const lastPoint =
                currentStroke.value.points[
                    currentStroke.value.points.length - 1
                ];
            currentStroke.value.points = [startPoint.value, lastPoint];
        }

        emit("draw", currentStroke.value);
    }

    currentStroke.value = null;
    startPoint.value = null;
};

const handleTouchStart = (event) => {
    const touch = event.touches[0];
    startDrawing({ clientX: touch.clientX, clientY: touch.clientY });
};

const handleTouchMove = (event) => {
    const touch = event.touches[0];
    draw({ clientX: touch.clientX, clientY: touch.clientY });
};

// Handle resize
let resizeObserver = null;

onMounted(() => {
    nextTick(() => {
        initCanvas();

        resizeObserver = new ResizeObserver(() => {
            initCanvas();
        });

        if (canvas.value?.parentElement) {
            resizeObserver.observe(canvas.value.parentElement);
        }
    });
});

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
    }
});

// Redraw when strokes change
watch(
    () => props.strokes,
    () => {
        redrawCanvas();
    },
    { deep: true },
);
</script>
