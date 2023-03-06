<template>
    <div class="alarm">
      <el-dialog
        title="Levels"
        :visible.sync="visible"
        width="50%"
        center>
  
        <el-table
          :data="data"
          stripe
          style="width: 100%"
          v-loading="!data.length">
          <el-table-column
            label="Name"
            min-width="30">
            <template slot-scope="scope">
              <span @click="getLevelEvents(scope.$index, scope.row)" class="btn">{{ scope.row.Name }}</span>
            </template>
          </el-table-column>
          <el-table-column
            prop="Date"
            label="Date"
            min-width="20">
          </el-table-column>
          <el-table-column
            prop="Source"
            label="Source"
            min-width="20">
          </el-table-column>
          <el-table-column
            prop="Destination"
            label="Destination"
            min-width="20">
          </el-table-column>
          <el-table-column
            prop="Level"
            label="Level"
            min-width="10">
          </el-table-column>
        </el-table>
  
        <el-dialog
          width="40%"
          title="Events"
          :visible.sync="levelEventsVisible"
          :before-close="levelEventsClose"
          append-to-body
          center>
          <el-table
            :data="eventsData"
            stripe
            style="width: 100%"
            @expand-change="expandChange">
            <el-table-column type="expand">
              <template slot-scope="props">
                <el-table
                  :data="eventsDetailData"
                  border
                  style="width: 100%">
                  <el-table-column
                    prop="key"
                    label="Fields"
                    min-width="50">
                  </el-table-column>
                  <el-table-column
                    prop="value"
                    label="Values"
                    min-width="50">
                  </el-table-column>
                </el-table>
              </template>
            </el-table-column>
            <el-table-column
              prop="Name"
              label="Name"
              min-width="25">
            </el-table-column>
            <el-table-column
              prop="Date"
              label="Date"
              min-width="25">
            </el-table-column>
            <el-table-column
              prop="Source"
              label="Source"
              min-width="25">
            </el-table-column>
            <el-table-column
              prop="Destination"
              label="Destination"
              min-width="25">
            </el-table-column>
          </el-table>
  
          <el-pagination
                @size-change="handleEventsSizeChange"
                @current-change="handleEventsCurrentChange"
                :current-page.sync="eventPagination.currentPage"
                :page-sizes="eventPagination.pageSizes"
                :page-size="eventPagination.pageSize"
                layout="total, sizes , prev, pager, next"
                :total="levelEventsData.length">
          </el-pagination>
  
        </el-dialog>
      </el-dialog>
    </div>
  </template>
  
  
  <script>
  
    import store from '../../store';
  
    export default {
      data() {
        return {
          levelEventsVisible: false,
          levelEventsData: [],
          eventsDetailData:[],
          eventsData: [],
          eventPagination: {
            pageSizes: [5 , 10 , 15 , 20],
            pageSize: 5,
            currentPage: 1
          }
        }
      },
  
      methods: {
  
        getLevelEvents(index , raw) {
          this.levelEventsData = this.data[index].Events;
          this.levelEventsVisible = true;
          this.sliceEventsData();
        },
  
        expandChange(row , expandedRow) {
          this.eventsDetailData = Object.entries(row).map(item => {
            return {
              key: item[0],
              value: item[1]
            }
          });
        },
  
        sliceEventsData(data) {
          this.eventsData = this.levelEventsData.slice((this.eventPagination.currentPage - 1) * this.eventPagination.pageSize,
                                                        this.eventPagination.currentPage * this.eventPagination.pageSize);
        },
  
        levelEventsClose(done) {
          this.eventPagination.pageSize = this.eventPagination.pageSizes[0];
          done();
        },
  
        handleEventsCurrentChange(page) {
          this.eventPagination.currentPage = page;
          this.sliceEventsData();
        },
  
        handleEventsSizeChange(size) {
          this.eventPagination.pageSize = size;
          this.sliceEventsData();
        }
      },
  
      computed: {
        data() {
          return store.state.eventResult;
        },
  
        visible: {
          get: () => store.state.eventDialog,
          set: () => store.commit('showEventDialog' , false)
        }
      },
  
      created() {
  
      },
  
      mounted() {
  
      },
  
      components: {
  
      }
    }
  </script>
  