import Vue from 'vue';
import Vuex from 'vuex';
import Form from "../../classes/Form";
import SubscribeMutations from './plugins/SubscribeMutations';
import { AGGREGATIONS } from './constants/Aggregations';
import { ROUTER_PARAMS } from './constants/RouterParams';
import { COLUMNS } from './constants/ResultColumns';

Vue.use(Vuex)


function initialState() {
  return {
    form: new Form({
      address: {
        source_ip_option: '=',
        source_ip: '',
        join_option: 'And',
        destination_ip_option: '=',
        destination_ip: ''
      },
      port: {
        source_port_option: '=',
        source_port: '',
        join_option: 'And',
        destination_port_option: '=',
        destination_port: ''
      },
      collector: '',
      scenario_name: '',
      risk: {
        option: '=',
        value: ''
      },
      event_count: {
        option: '=',
        value: ''
      },
      aggregation: '',
      date: {
        all: false,
        value: [
          Moment().startOf('day').format("YYYY-MM-DD HH:mm:ss") ,
          Moment().endOf('day').format("YYYY-MM-DD HH:mm:ss")]
      },
      status: 'open',
      page: 1,
      limit: 10,
      sort: {
        prop: 'alarm_date',
        order: 'desc'
      }
    }),
    formErrors: {},
    searchResult: [],
    eventResult: [],
    eventDialog: false,
    ticketDialog: {
      visible: false,
      alarm  : {}
    },
    activeTab: 'search',
    disabledTabs: {
      aggregationTab: true,
      searchTab: false
    },
    timeInterval: 10,
    timeIntervalId: '',
    loading: true,
    beep: false,
    enableBeep: false,
    selectedAlarms: [],
    resultColumns: setResultColumnsToDefaultState()
  }
}

function setResultColumnsToDefaultState() {
  let selected = COLUMNS.filter(column => column.selected);
  return {
    search: selected,
    live: selected
  }
}

function getCopiedFormForAggregatedAlarmsActions(state) {
  let copiedForm = JSON.parse(JSON.stringify(state.form));
  let aggregatedTerms = {
    field: copiedForm.aggregation,
    terms: state.selectedAlarms.map(item => item.key)
  }
  
  copiedForm.aggregatedTerms = aggregatedTerms;
  copiedForm.aggregation = '';

  return copiedForm;
}

const store = new Vuex.Store({
  plugins: [SubscribeMutations],

  state: initialState,

  mutations: {
    setSearchResult(state , data) {
      if (state.beep) {
        let oldDataTotal = state.searchResult.pagination.total_count;
        if (data.pagination.total_count > oldDataTotal) {
          state.enableBeep = true;
        }
        else {
          state.enableBeep = false;
        }
      }
      state.searchResult = data;
      state.loading = false;
    },

    setSearchResultPage(state , page) {
      state.form.page = page;
    },

    setSearchResultLimit(state , limit) {
      state.form.limit = limit;
    },

    setSearchForm(state , form) {
      form.date.value = [
        Moment(form.date.value[0]).format("YYYY-MM-DD HH:mm:ss"),
        Moment(form.date.value[1]).format("YYYY-MM-DD HH:mm:ss")
      ]
      state.form = form;
    },

    setSearchSort(state , sort) {
      state.form.sort = sort;
    },

    setEventResult(state , result) {
      state.eventResult = result;
    },

    showEventDialog(state , show) {
      state.eventResult = [];
      state.eventDialog = show;
    },

    setEventResultPage(state , page) {
      state.selectedAlarm.page = page;
    },

    setEventResultLimit(state , limit) {
      state.selectedAlarm.limit = limit;
    },

    showTicketDialog(state , params) {
      state.ticketDialog = {
        visible: params.visible,
        alarm  : params.alarm
      };
    },

    setActiveTab(state , tabName) {
      state.activeTab = tabName;
      state.selectedAlarms = [];
      state.form.page = 1;
      state.form.limit = 10;
      if (tabName === 'aggregation') {
        state.searchResult = [];
        state.form.sort = {
          prop: 'doc_count',
          order: 'desc'
        }
        state.disabledTabs = {
          searchTab: true,
          aggregationTab: false
        }
      }
      else {
        state.form.sort = {
          prop: 'alarm_date',
          order: 'desc'
        }
        state.disabledTabs = {
          searchTab: false,
          aggregationTab: true
        }
      }
    },

    setAggregationSearchField(state , value) {
      let item = AGGREGATIONS.find(item => item.name === state.form.aggregation);
      _.set(state.form , item.formField , value);
      state.form.aggregation = '';
    },

    setTimeInterval(state , value) {
      state.timeInterval = value;
    },

    clearIntervalId(state) {
      clearInterval(state.timeIntervalId);
    },

    setTimeIntervalId(state , cb) {
      state.timeIntervalId = cb;
    },

    enableLoading(state) {
      state.loading = true;
    },

    setLiveFormDate(state , date) {
      state.form.date.value = date;
    },

    setBeepOption(state, value) {
      state.beep = value;
    },

    resetStoreState(state) {
      const initial = initialState();
      Object.keys(initial).forEach(key => {
        state[key] = initial[key];
      })
    },

    clearFormFields(state) {
      const initial = initialState();
      Object.keys(initial.form).forEach(key => {
        state.form[key] = initial.form[key];
      });
      state.formErrors = {};
    },

    mapRouterParamsToForm(state , params) {
      state.form.status = Object.keys(params).length ? '' : 'Open';
      for (let [key , value] of Object.entries(params)) {
        let item = ROUTER_PARAMS.find(item => item.parameter === key);
        _.set(state.form , item.formField , value);
      }
    },

    setFormErrors(state , errors) {
      state.formErrors = errors;
    },

    setSelectedAlarms(state, alarms) {
      state.selectedAlarms = alarms
    },

    setResultColumns(state, values) {
      state.resultColumns[values.tab] = values.columns;
    }
  },

  actions: {
    getSearchResult(context) {
      context.commit('enableLoading');

      window.axios.post('alarms', context.state.form).then(response => {
        let total_count = response.data.pagination.total_count;
        let total_pages = total_count < 10000 ? total_count : 10000;
        let result = {
          data: response.data.data,
          pagination: { ...response.data.pagination, ...{ total_pages }}
        };
        context.commit('setSearchResult' , result);
      }).catch(error => {
        context.commit('setFormErrors', error.data.errors);
        //show error
        window.swal({
          title:`${error.data.message}`,
          text: 'Please contact administrator',
          type: 'error',
          timer: false,
        })
      })
    },

    getEventResult(context , alarm) {
      context.commit('showEventDialog' , true);
      window.axios.post(`alarms/${alarm.id}`).then(response => {
        context.commit('setEventResult' , response.data);
      }).catch(error => {
        dd(error);
      })
    },

    getLiveSearchResult(context) {
      let cb = setInterval(() => {
        if (! context.state.form.date.all) {
          let formDate = context.state.form.date.value;
          let start = formDate[0];
          let end = Moment().format("YYYY-MM-DD HH:mm:ss");
          context.commit('setLiveFormDate' , [start , end]);
        }
        context.dispatch('getSearchResult');
        } , context.state.timeInterval * 1000);

      context.commit('setTimeIntervalId' , cb);
    },

    changeSelectedAlarmsStatus({commit , state}) {
      let parameters = {};

      if (state.activeTab === 'aggregation') {
        let copiedForm = getCopiedFormForAggregatedAlarmsActions(state);
        parameters = {
          type: 'updateAlarmsByQuery',
          data: copiedForm
        };
      }
      else {

        parameters = {
          type: 'updateAlarmsByDocuments',
          data: state.selectedAlarms
        };
      }

      return window.axios.post(`change_selected_alarms_status`, {parameters});
    },

    deleteSeletedAlarms({ commit, state }) {
      let parameters = {};
      
      if (state.activeTab === 'aggregation') {
        let copiedForm = getCopiedFormForAggregatedAlarmsActions(state);
        parameters = {
          type: 'deleteAlarmsByQuery',
          data: copiedForm
        };
      }
      else {
        parameters = {
          type: 'deleteAlarmsByDocuments',
          data: state.selectedAlarms
        }
      }

      return window.axios.post(`destroy_selected_alarms`, {parameters});
    }
  }
})


export default store;
