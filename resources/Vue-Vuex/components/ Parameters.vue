<template>
    <div class="alarm col-12 mb-2">
        <el-form label-width="133px">
            <div>
                <div class="row">
                    <div class="col-12 ">
                        <form-header collapsible target="#parameters" title="Search Parameters" />
                    </div>
                </div>
                <div style="background-color:#fbfbfb;">
                    <div id="parameters" style="padding:50px;">
                        <div class="row justify-content-start">
                            <div class="col-md-12">
                                <el-form-item label="Address">
                                    <div class="d-flex">
                                        <el-form-item class="operand" :error="getErrors('address.source_ip')">
                                            <el-input placeholder="Source IP" v-model="form.address.source_ip">
                                                <el-select v-model="form.address.source_ip_option" slot="prepend">
                                                    <el-option v-for="(option , index) in queryOptions" :key="index" :label="option.label" :value="option.value">
                                                    </el-option>
                                                </el-select>
                                            </el-input>
                                        </el-form-item>
                                        <el-select v-model="form.address.join_option" class="operator">
                                            <el-option v-for="item in joinOptions" :key="item.value" :label="item.label" :value="item.value">
                                            </el-option>
                                        </el-select>
                                        <el-form-item class="operand" :error="getErrors('address.destination_ip')">
                                            <el-input placeholder="Destination IP" v-model="form.address.destination_ip">
                                                <el-select v-model="form.address.destination_ip_option" slot="prepend">
                                                    <el-option v-for="(option , index) in queryOptions" :key="index" :label="option.label" :value="option.value">
                                                    </el-option>
                                                </el-select>
                                            </el-input>
                                        </el-form-item>
                                    </div>
                                </el-form-item>
                            </div>
                        </div>
    
                        <div class="row justify-content-start">
                            <div class="col-md-12">
                                <el-form-item label="Ports">
                                    <div class="d-flex">
                                        <el-form-item class="operand" :error="getErrors('port.source_port')">
                                            <el-input placeholder="Source Port" v-model="form.port.source_port">
                                                <el-select v-model="form.port.source_port_option" slot="prepend">
                                                    <el-option v-for="(option , index) in queryOptions" :key="index" :label="option.label" :value="option.value">
                                                    </el-option>
                                                </el-select>
                                            </el-input>
                                        </el-form-item>
                                        <el-select v-model="form.port.join_option" class="operator">
                                            <el-option v-for="item in joinOptions" :key="item.value" :label="item.label" :value="item.value">
                                            </el-option>
                                        </el-select>
                                        <el-form-item class="operand" :error="getErrors('port.destination_port')">
                                            <el-input placeholder="Destination Port" v-model="form.port.destination_port">
                                                <el-select v-model="form.port.destination_port_option" slot="prepend">
                                                    <el-option v-for="(option , index) in queryOptions" :key="index" :label="option.label" :value="option.value">
                                                    </el-option>
                                                </el-select>
                                            </el-input>
                                        </el-form-item>
                                    </div>
                                </el-form-item>
                            </div>
                        </div>
    
                        <div class="row d-flex justify-content-start">
                            <div class="col-md-5">
                                <el-form-item label="Scenario Name">
                                    <el-autocomplete class="w-100" v-model="form.scenario_name" :fetch-suggestions="querySearch" placeholder="Input scenario name" :trigger-on-focus="false">
                                    </el-autocomplete>
                                </el-form-item>
                            </div>
                            <div class="col-md-1"></div>
                            <div class="col-md-5">
                                <el-form-item label="Collector">
                                    <el-select clearable v-model="form.collector" placeholder="Select Collector">
                                        <el-option v-for="collector in collectors" :key="collector.id" :label="collector.name" :value="collector.name">
                                        </el-option>
                                    </el-select>
                                </el-form-item>
                            </div>
                        </div>
    
                        <div class="row d-flex justify-content-start">
                            <div class="col-md-5">
                                <el-form-item label="Risk" :error="getErrors('risk.value')">
                                    <el-input placeholder="Risk" v-model="form.risk.value">
                                        <el-select v-model="form.risk.option" slot="prepend">
                                            <el-option v-for="(option , index) in comparisonOptions" :key="index" :label="option.label" :value="option.value">
                                            </el-option>
                                        </el-select>
                                    </el-input>
                                </el-form-item>
                            </div>
                            <div class="col-md-1"></div>
    
                            <div class="col-md-5">
                                <el-form-item label="Number of events" :error="getErrors('event_count.value')">
                                    <el-input placeholder="Number of events" v-model="form.event_count.value">
                                        <el-select v-model="form.event_count.option" slot="prepend">
                                            <el-option v-for="(option , index) in comparisonOptions" :key="index" :label="option.label" :value="option.value">
                                            </el-option>
                                        </el-select>
                                    </el-input>
                                </el-form-item>
                            </div>
                        </div>
    
                        <div class="row d-flex justify-content-start">
                            <div class="col-md-5">
                                <el-form-item label="Aggregation">
                                    <el-select v-model="form.aggregation" @change="aggregationChanged">
                                        <el-option v-for="(aggregation , index) in aggregations" :key="index" :label="aggregation" :value="aggregation">
                                        </el-option>
                                    </el-select>
                                </el-form-item>
                            </div>
                            <div class="col-md-1"></div>
    
                            <div class="col-md-5">
                                <el-form-item label="Date">
                                    <el-date-picker  v-model="form.date.value" :disabled="form.date.all" type="datetimerange" range-separator="To" >
                                    </el-date-picker>
                                </el-form-item>
                            </div>
                        </div>
    
                        <div class="row d-flex justify-content-start">
                            <div class="col-md-5">
                                <el-form-item label="Status">
                                    <el-select v-model="form.status">
                                        <el-option v-for="(status , index) in statusOptions" :key="index" :label="status.label" :value="status.value">
                                        </el-option>
                                    </el-select>
                                </el-form-item>
                            </div>
                            <div class="col-md-1"></div>
    
                            <div class="col-md-2">
                                <el-form-item label="All Dates">
                                    <el-switch v-model="form.date.all" class="m-1"></el-switch>
                                </el-form-item>
                            </div>
                            <div class="col-md-2">
                                <el-form-item label="Beep">
                                    <el-switch v-model="beep" class="m-1"></el-switch>
                                </el-form-item>
                            </div>
                        </div>
    
                        <div class="row text-center m-3">
                            <div class="col-4"></div>
                            <div class="col-2">
                                <el-button class="w-100" type="primary" @click="search" plain>Submit</el-button>
                            </div>
                            <div class="col-2">
                                <el-button class="w-100" type="danger" @click="clearAll" plain>Clear</el-button>
                            </div>
                            <div class="col-4"></div>
                        </div>
    
                    </div>
                </div>
            </div>
        </el-form>
    </div>
    </template>
    
    <script>
    import FormHeader from '../../../components/FormHeader';
    import { AGGREGATIONS } from '../constants/Aggregations';
    import store from '../store';
    
      export default {
        data() {
          return {
              queryOptions: [
                {
                    value: '=',
                    label: '='
                },
                {
                    value: '!=',
                    label: 'NOT'
                }
              ],
              joinOptions: [
                  {
                      value: 'And',
                      label: 'And'
                  },
                  {
                      value: 'Or',
                      label: 'Or'
                  }
              ],
    
              collectors: [],
              scenario_names: [],
              comparisonOptions: [
                {
                  label : '<=',
                  value : '<='
                },
                {
                  label: '>=',
                  value: '>='
                },
                {
                  label: '=',
                  value: '='
                }
              ],
    
              statusOptions: [
                {
                  label: 'All',
                  value: ''
                },
                {
                  label: 'Open',
                  value: 'open'
                },
                {
                  label: 'Closed',
                  value: 'closed'
                }
              ]
          }
        },
    
        methods: {
          getCollectors() {
            window.axios.get('alarms_collector').then(response => {
                this.collectors = response.data.data;
            })
            .catch(error => {
              dd(error);
            })
          },
    
          addScenarioNames() {
            window.axios.get(`/alarms_directive_names`).then(response => {
              this.scenario_names = response.data.data.map(item => {
                return {
                  value: item.name
                };
              });
            });
          },
    
          querySearch(queryString, cb) {
            let results = queryString ? this.scenario_names.filter(this.createFilter(queryString)) : this.scenario_names;
            cb(results);
          },
    
          createFilter(queryString) {
            return (item) => {
              return (item.value.toLowerCase().indexOf(queryString.toLowerCase()) === 0);
            };
          },
    
          clearAll() {
            store.commit('clearFormFields');
          },
    
          search() {
            store.commit('setSearchForm' , this.form);
          },
    
          aggregationChanged(value) {
            if (value) {
              store.commit('setActiveTab' , 'aggregation');
            }
          },
    
          getErrors(key) {
            return store.state.formErrors[key]?.[0];
          }
        },
    
        computed: {
          form() {
            return store.state.form;
          },
    
          aggregations() {
            return AGGREGATIONS.map(item => item.name);
          },
    
          beep: {
            get: () => store.state.beep,
            set: (value) => store.commit('setBeepOption' , value)
          }
        },
    
        created() {
          this.getCollectors();
          this.addScenarioNames();
        },
    
        mounted() {
    
        },
    
        components: {
          FormHeader
        }
      }
    </script>
    
    <style scoped>
    
    </style>
    
    
    