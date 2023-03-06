<template>
    <div class="alarm ml-1">
     <el-select 
      v-model="values"
      value-key="prop" 
      multiple 
      filterable 
      collapse-tags
      placeholder="Select columns">
         <el-option
         v-for="item in options"
         :key="item.prop"
         :label="item.label"
         :value="item"/>
     </el-select>
   </div>
 </template>
 
 <script>
 import store from "../store";
 import { COLUMNS } from "../constants/ResultColumns"
 
   export default {
     data() {
         return {
 
         }
     },
 
     computed: {
         values: {
             get: () => store.state.resultColumns[store.state.activeTab],
             set: (value) => store.commit('setResultColumns' , {
                 tab: store.state.activeTab,
                 columns: value
             })
         },
 
         options() {
             return COLUMNS
         }
     }
   }
 </script>
 