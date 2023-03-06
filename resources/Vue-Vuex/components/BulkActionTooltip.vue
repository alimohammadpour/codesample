<template>
    <div class="alarm ml-1" v-if="selectedAlarms.length">
      <el-tooltip content="Change selected alarms status" placement="top">
          <el-button @click="changeSelectedAlarmsStatus()" type="info" plain><i class="fa fa-check"></i></el-button>
      </el-tooltip>
      <el-tooltip content="Delete selected alarms" placement="top">
          <el-button @click="deleteSeletedAlarms()" type="danger" plain><i class="fa fa-trash"></i></el-button>
      </el-tooltip>
    </div>
  </template>
  
  
  <script>
  
    import store from '../store';
  
    export default {
      data() {
        return {
          
        }
      },
  
      methods: {
          changeSelectedAlarmsStatus() {
              window
              .swal({
                  title: 'Are you sure you want to change selected alarms status?',
                  type: 'warning',
                  showCancelButton: true,
                  confirmButtonText: 'Change',
                  timer: false,
              })
              .then(
                  (isConfirm)=> {
                      if (isConfirm.value) {
                          store.dispatch('changeSelectedAlarmsStatus')
                          .then(response => {
                          swal(
                              'Done',
                              'Alarms updated successfully!',
                              'success'
                          ).then(() => {
                              store.dispatch('getSearchResult');
                          }).catch(error => {
                              //
                          });
                          })
                      }
                      else if (isConfirm.dismiss === 'cancel') {
                          //
                      } else if (isConfirm.dismiss === 'esc') {
                          //
                      }
                  });
        },
  
        deleteSeletedAlarms() {
          window
            .swal({
                title: 'Are you sure you want to delete selected alarms?',
                type: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                timer: false,
            })
            .then(
              (isConfirm)=> {
                  if (isConfirm.value) {
                    store.dispatch('deleteSeletedAlarms')
                    .then(response => {
                      swal(
                        'Done',
                        'Alarms deleted successfully!',
                        'success'
                      ).then(() => {
                        store.dispatch('getSearchResult');
                      }).catch(error => {
                          //
                      });
                    })
                  }
                  else if (isConfirm.dismiss === 'cancel') {
                      //
                  } else if (isConfirm.dismiss === 'esc') {
                      //
                  }
              });
        }
      },
  
      computed: {
          selectedAlarms() {
              return store.state.selectedAlarms;
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
  