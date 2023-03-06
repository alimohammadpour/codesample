<template>
    <div class="alarm mb-2">
        <div class="d-flex">
            <bulk-action-tooltip />
            <columns class="ml-auto w-25" />
        </div>
        <el-table :data="data" @selection-change="selectionChanged" @sort-change="sortChanged" v-loading="loading" style="width: auto;">
            <el-table-column type="selection"></el-table-column>
            <el-table-column label="Alarm" min-width="300">
                <template slot-scope="scope">
                    <span @click="loadEvents(scope.$index, scope.row)" class="btn">{{ scope.row.alarm }}</span>
                </template>
            </el-table-column>
            <el-table-column v-for="(col, index) in columns" :label="col.label" :key="index" :prop="col.prop" :sortable="col.sortable ? 'custom' : false">
            </el-table-column>
            <el-table-column align="right" label="Actions" min-width="150">
                <template slot-scope="scope">
                    <el-button-group>
                        <el-button v-if="scope.row.status=='open'" size="mini" title="Close" type="warning" @click="closeAlarm(scope.$index, scope.row)" plain><i class="fa fa-close"></i></el-button>
                        <el-button v-else size="mini" title="Open" type="success" @click="openAlarm(scope.$index, scope.row)" plain><i class="fa fa-check"></i></el-button>
                        <el-button size="mini" type="danger" @click="handleDelete(scope.$index, scope.row)" title="Delete Alarm" plain><i class="fa fa-trash"></i></el-button>
                        <el-button size="mini" type="success" @click="openTicket(scope.$index, scope.row)" title="Open Ticket" plain><i class="fa fa-ticket"></i></el-button>
                    </el-button-group>
                </template>
            </el-table-column>
        </el-table>
        <el-pagination v-if="pagination" @size-change="handleSizeChange" @current-change="handleCurrentChange" :page-sizes="[10, 20, 40, 80, 100]" :current-page.sync="pagination.current_page" :page-size="pagination.limit" layout="sizes , prev, pager, next, jumper, slot" :total="pagination.total_pages">
            <span class="pagination-total-slot">Total: {{ pagination.total_count }}</span>
        </el-pagination>
    
        <open-ticket-dialog></open-ticket-dialog>
    </div>
    </template>
    
    <script>
    import store from "../../store";
    import OpenTicketDialog from "../OpenTicketDialog";
    import BulkActionTooltip from "../BulkActionTooltip";
    import Resultcolumns from "../ResultColumns.vue";
    
    export default {
        data() {
            return {};
        },
    
        methods: {
            handleDelete(index, row) {
                window
                    .swal({
                        title: "Are you sure you want to delete selected alarm?",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Delete",
                        timer: false,
                    })
                    .then((isConfirm) => {
                        if (isConfirm.value) {
                            window.axios
                                .post(`alarms_destroy`, {
                                    id: row.id,
                                    index: row.index,
                                })
                                .then((response) => {
                                    swal(
                                            "Done",
                                            "Alarm deleted successfully!",
                                            "success"
                                        )
                                        .then(() => {
                                            store.dispatch("getSearchResult");
                                        })
                                        .catch((error) => {
                                            //
                                        });
                                });
                        } else if (isConfirm.dismiss === "cancel") {
                            //
                        } else if (isConfirm.dismiss === "esc") {
                            //
                        }
                    });
            },
    
            openTicket(index, row) {
                store.commit("showTicketDialog", {
                    visible: true,
                    alarm: row
                });
            },
    
            loadEvents(index, row) {
                store.dispatch("getEventResult", row);
            },
    
            handleSizeChange(limit) {
                store.commit("setSearchResultLimit", limit);
            },
    
            handleCurrentChange(page) {
                store.commit("setSearchResultPage", page);
            },
    
            sortChanged(obj) {
                let sort = {
                    prop: obj.prop,
                    order: obj.order === "descending" ? "desc" : "asc",
                };
    
                store.commit("setSearchSort", sort);
            },
    
            closeAlarm(index, row) {
                window
                    .swal({
                        title: "Are you sure you want to close the alarm status?",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Change",
                        timer: false,
                    })
                    .then((isConfirm) => {
                        if (isConfirm.value) {
                            window.axios
                                .post(`alarms_change_status`, {
                                    id: row.id,
                                    index: row.index,
                                    newStatus: "close",
                                })
                                .then((response) => {
                                    swal(
                                            "Done",
                                            "Alarm closed successfully!",
                                            "success"
                                        )
                                        .then(() => {
                                            store.dispatch("getSearchResult");
                                        })
                                        .catch((error) => {
                                            //
                                        });
                                          })
                                    .catch((error) => {
                                        window
                                            .swal({
                                                title: "Error",
                                                text:error.data.message,
                                                type: "error",
                                                showCancelButton: true,
                                                confirmButtonText: "OK",
                                                timer: false,
                                            })
                                    });
                        } else if (isConfirm.dismiss === "cancel") {
                            //
                        } else if (isConfirm.dismiss === "esc") {
                            //
                        }
                    });
            },
    
            openAlarm(index, row) {
                window
                    .swal({
                        title: "Are you sure you want to open the alarm status?",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Change",
                        timer: false,
                    })
                    .then((isConfirm) => {
                        if (isConfirm.value) {
                            window.axios
                                .post(`alarms_change_status`, {
                                    id: row.id,
                                    index: row.index,
                                    newStatus: "open",
                                })
                                .then((response) => {
                                    swal(
                                            "Done",
                                            "Alarm opened successfully!",
                                            "success"
                                        )
                                        .then(() => {
                                            store.dispatch("getSearchResult");
                                        })
                                        .catch((error) => {
                                            //
                                        });
                                })
                                 .catch((error) => {
                                     window
                                        .swal({
                                            title: "Error",
                                            text:error.data.message,
                                            type: "error",
                                            showCancelButton: true,
                                            confirmButtonText: "OK",
                                            timer: false,
                                        })
                                 });
                        } else if (isConfirm.dismiss === "cancel") {
                            //
                        } else if (isConfirm.dismiss === "esc") {
                            //
                        }
                    });
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
    
            loading() {
                return store.state.loading;
            },
    
            columns() {
                return store.state.resultColumns.search;
            },
        },
    
        created() {},
    
        mounted() {},
    
        components: {
            "open-ticket-dialog": OpenTicketDialog,
            "bulk-action-tooltip": BulkActionTooltip,
            columns: Resultcolumns,
        },
    };
    </script>
    