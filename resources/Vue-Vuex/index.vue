<template>
    <div class="alarm">
        <div class="row card">
            <div class="col-md-12">
                <parameters ref="parameters"></parameters>
                <el-tabs type="card" :active-name="activeTab" @tab-click="tabClick">
                  <el-tab-pane label="Search" name="search" :disabled="searchTabDisabled"><search></search></el-tab-pane>
                  <el-tab-pane label="Live" name="live" :disabled="searchTabDisabled"><live></live></el-tab-pane>
                  <el-tab-pane label="Aggregation" name="aggregation" :disabled="aggregationTabDisabled"><aggregation></aggregation></el-tab-pane>
                </el-tabs>
            </div>
        </div>
  
        <event-dialog></event-dialog>
    </div>
  </template>
  
  
  <script>
  
    import Parameters from './components/Parameters';
    import SearchResult from './components/results/SearchResult';
    import AggregationResult from './components/results/AggregationResult';
    import LiveResult from './components/results/LiveResult';
    import EventResult from './components/results/EventResult';
    import store from './store';
  
    export default {
      data() {
        return {
  
        }
      },
  
      methods: {
        tabClick(tab) {
          store.commit('setActiveTab' , tab.name);
        }
      },
  
      computed: {
        activeTab() {
          return store.state.activeTab;
        },
  
        aggregationTabDisabled() {
          return store.state.disabledTabs.aggregationTab;
        },
  
        searchTabDisabled() {
          return store.state.disabledTabs.searchTab;
        }
      },
  
      created() {
        store.commit('mapRouterParamsToForm' , this.$route.params);
      },
  
      beforeDestroy() {
        store.commit('resetStoreState');
      },
  
      components: {
        'parameters' : Parameters,
        'search' : SearchResult,
        'aggregation' : AggregationResult,
        'live' : LiveResult,
        'event-dialog' : EventResult
      }
    }
  </script>
  