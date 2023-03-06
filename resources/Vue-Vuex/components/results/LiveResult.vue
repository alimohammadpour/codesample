<template>
    <div class="alarm mb-2">
        <div class="d-flex justify-content-end">
            <columns class="w-25" />
            <el-select v-model="timeInterval" class="w-25">
                <el-option
                    v-for="(interval, index) in intervals"
                    :key="index"
                    :label="interval.label"
                    :value="interval.value"
                >
                </el-option>
            </el-select>
            <el-button
                size="small"
                type="info"
                @click="toggleFullScreen"
                title="FullScreen"
                plain
            >
                <i class="fa el-icon-full-screen"></i>
            </el-button>
        </div>

        <el-table
            :data="data"
            v-loading="loading"
            :row-style="rowStyle"
            class="live-table w-100"
        >
            <el-table-column label="Alarm" min-width="300">
                <template slot-scope="scope">
                    <span
                        @click="loadEvents(scope.$index, scope.row)"
                        class="btn"
                        >{{ scope.row.alarm }}</span
                    >
                </template>
            </el-table-column>
            <el-table-column
                v-for="(col, index) in columns"
                :label="col.label"
                :key="index"
                :prop="col.prop"
                :sortable="col.sortable ? 'custom' : false"
            >
            </el-table-column>
        </el-table>

        <el-pagination
            v-if="pagination"
            @size-change="handleSizeChange"
            :page-sizes="[10, 20, 40, 80, 100]"
            :current-page.sync="pagination.current_page"
            :page-size="pagination.limit"
            layout="total, sizes"
            :total="pagination.total_count"
        >
        </el-pagination>
    </div>
</template>

<script>
import store from "../../store";
import fullscreen from "vue-fullscreen";
import Resultcolumns from "../ResultColumns.vue";

Vue.use(fullscreen);

export default {
    data() {
        return {
            intervals: [
                {
                    label: "5 sec",
                    value: 5,
                },
                {
                    label: "10 sec",
                    value: 10,
                },
                {
                    label: "60 sec",
                    value: 60,
                },
                {
                    label: "120 sec",
                    value: 120,
                },
            ],

            fullscreen: false,

            rowStyle: {
                height: "auto",
            },
        };
    },

    methods: {
        loadEvents(index, row) {
            store.dispatch("getEventResult", row);
        },

        handleSizeChange(limit) {
            store.commit("setSearchResultLimit", limit);
        },

        toggleFullScreen() {
            this.$fullscreen.toggle(this.$el.querySelector(".live-table"), {
                wrap: false,
                callback: this.fullscreenChange,
            });
        },

        fullscreenChange(fullscreen) {
            let length = window.innerHeight / (this.data.length + 1);
            this.rowStyle.height =
                fullscreen && length > 50 ? length + "px" : "auto";
            this.fullscreen = fullscreen;
        },
    },

    computed: {
        data() {
            return store.state.searchResult.data;
        },

        pagination() {
            if (store.state.enableBeep) {
                let audio = new Audio("../../../../../../audios/beep.wav");
                audio.play();
            }
            return store.state.searchResult.pagination;
        },

        timeInterval: {
            get: () => store.state.timeInterval,
            set: (value) => store.commit("setTimeInterval", value),
        },

        loading() {
            return store.state.loading;
        },

        columns() {
            return store.state.resultColumns.live;
        },
    },

    created() {},

    mounted() {},

    components: {
        columns: Resultcolumns,
    },
};
</script>
