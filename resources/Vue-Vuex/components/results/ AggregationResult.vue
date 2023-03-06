<template>
    <div class="alarm mb-2">
        <bulk-action-tooltip></bulk-action-tooltip>

        <el-table
            :data="data"
            @selection-change="selectionChanged"
            @sort-change="sortChanged"
            v-loading="loading"
            style="width: 100%"
        >
            <el-table-column type="selection" width="55"> </el-table-column>
            <el-table-column :label="aggregationLabel">
                <template slot-scope="scope">
                    <span
                        @click="loadAlarms(scope.$index, scope.row)"
                        class="btn"
                        >{{ scope.row.key }}</span
                    >
                </template>
            </el-table-column>
            <el-table-column
                prop="doc_count"
                label="Occurrence"
                sortable="custom"
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
import BulkActionTooltip from "../BulkActionTooltip";

export default {
    data() {
        return {};
    },

    methods: {
        handleSizeChange(limit) {
            store.commit("setSearchResultLimit", limit);
        },

        sortChanged(obj) {
            let sort = {
                prop: obj.prop,
                order: obj.order === "descending" ? "desc" : "asc",
            };

            store.commit("setSearchSort", sort);
        },

        loadAlarms(index, raw) {
            store.commit("setAggregationSearchField", raw.key);
        },

        selectionChanged(val) {
            store.commit("setSelectedAlarms", val);
        },
    },

    computed: {
        data() {
            return store.state.searchResult.data;
        },

        pagination() {
            return store.state.searchResult.pagination;
        },

        aggregationLabel() {
            return store.state.form.aggregation || "-";
        },

        loading() {
            return store.state.loading;
        },
    },

    created() {},

    mounted() {},

    components: {
        "bulk-action-tooltip": BulkActionTooltip,
    },
};
</script>
