import { Button, Card, Form, Input, Select, Table } from 'antd';
import { ColumnsType } from 'antd/es/table/interface';
import React, { useEffect, useState } from 'react';
import { fetchGetAttacksRequest } from '../../actions/attacksAction';
import { appState } from '../../reducers/rootReducer';
import { useDispatch } from 'react-redux';
import { AttacksQueryParams, AttacksSearchField, AttacksState } from '../../actions/types/attacksTypes';
import { useSelector } from 'react-redux';
import { Attack as AttackType } from '../../actions/types/attacksTypes';
import { PaginationProps } from 'antd/es/pagination';
import { SearchOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';

const mappedAttacksWithKeys = (attacks: AttackType[]) => attacks.map((attack: AttackType, key: number) => ({
    key,
    ...attack
}));

interface TableOnChangeProps {
    current: number; 
    pageSize: number
}

const attacksStateSelector = (state: appState) => state.attacks;

const attackFieldsNames: { name: string; field: string }[] = [
    {
        name : 'Source IP',
        field: 'sourceIP'
    },
    {
        name : 'Source Port',
        field: 'sourcePort'
    },
    {
        name : 'Destination IP',
        field: 'destinationIP'
    },
    {
        name : 'Destination Port',
        field: 'destinationPort'
    }
] 

export const Attack = () => {
    const columns: ColumnsType<AttackType> = attackFieldsNames.map(item => ({
        title    : item.name,
        dataIndex: item.field
    }));

    const selectOptions: { label: string; value: string }[] = attackFieldsNames.map(item => ({
        label: item.name,
        value: item.field
    }));

    const navigate = useNavigate();

    const [ pagination, setPagination ] = useState<{ page: number, limit: number }>({
        page: 1,
        limit: 10
    });

    const [ searchField, setSearchField ] = useState<AttacksSearchField>({
        field: null,
        value: ''
    });

    const { pending, attacks, error }: AttacksState = useSelector(attacksStateSelector);

    const dispatch = useDispatch();

    useEffect(() => {
        const queryParams: AttacksQueryParams = { searchField, ...pagination };
        dispatch(fetchGetAttacksRequest({ queryParams }));
    }, [pagination]);

    const getDataSource = attacks ? mappedAttacksWithKeys(attacks.entities) : []; 

    let tablePagination: PaginationProps = {
        current: pagination.page,
        total  : attacks?.totalCount
    };

    const tableOnChange = (changes: unknown) => {
        const { current, pageSize } = changes as TableOnChangeProps;

        setPagination({
            page: current,
            limit: pageSize
        })
    }

    const selectionChanged = (selected: string) => {
        const attacksSearchField: AttacksSearchField = selected ? 
            {  ...searchField, field: selected } : 
            { field: null, value: '' };
        
        setSearchField(attacksSearchField);
    }

    const inputValueChanged = (event: React.ChangeEvent<HTMLInputElement>) => {
        setSearchField({
            ...searchField,
            value: event.target.value || ''
        })
    }

    const doSearch = () => {
        setPagination({
            ...pagination,
            page: 1
        });
    }

    const tableOnRow = (record: AttackType) => {
        return {
            onClick: () => navigate(`/attacks/${record.id}`)
        }
    }

    return (
        <Card title="Attacks">
            <Form layout='inline' style={{ justifyContent: 'center' }}>
                <Form.Item name='search' style={{ width: '30%', marginBottom: '15px' }}>
                    <Input.Group compact>
                        <Input  
                            placeholder='Enter an input value'
                            value={ searchField.value } 
                            style={{ width: '60%' }}
                            onChange={inputValueChanged}
                        />
                        <Select
                            allowClear
                            placeholder="Select a field"
                            options={selectOptions}
                            value={ searchField.field }
                            style={{ width: '40%' }}
                            onChange={selectionChanged}
                        />
                    </Input.Group>
                </Form.Item>
                <Form.Item>
                    <Button 
                        type='primary'
                        shape='round'
                        onClick={doSearch} 
                        icon={<SearchOutlined />} 
                    />
                </Form.Item>
            </Form>
            <Table 
                bordered
                columns={columns}
                dataSource={getDataSource}
                loading={pending}
                pagination={tablePagination}
                onChange={tableOnChange}
                onRow={tableOnRow}
            />
        </Card>
    )
}
