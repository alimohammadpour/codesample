import { useDispatch, useSelector } from 'react-redux';
import { useEffect } from "react";
import { appState } from '../../reducers/rootReducer';
import { fetchGetAttackRequest } from '../../actions/attackAction';
import { AttackDetailState } from '../../actions/types/attackTypes';
import { Button, Card, Collapse, Descriptions, Space, Spin } from 'antd';
import { RollbackOutlined } from '@ant-design/icons';
import ReactJson from 'react-json-view';
import { useNavigate, useParams } from 'react-router-dom';

interface AttackDetailProps {
    params  ?: { id: string | undefined };
    navigate?: any;
}

type ParsedDetailType = { 
    panelTitle?: string, 
    text       : string 
}[];

const attackStateSelector = (state: appState) => state.attack;

export const AttackDetail = () => {

    const routerParams = useParams();
    const navigate = useNavigate();

    const { pending, attack, error }: AttackDetailState = useSelector(attackStateSelector);

    const dispatch = useDispatch();

    useEffect(() => {
        const id = routerParams.id;
        if (id) dispatch(fetchGetAttackRequest({ id }));
    }, []);

    const getParagraphTexts = (values: ParsedDetailType): { text: string }[] =>  values.filter(detail => !detail.panelTitle);

    const getCollapsePanels = (values: ParsedDetailType): { [key: string]: [] } => values
        .filter(({panelTitle}) => panelTitle)
        .reduce((acc: any, { panelTitle, text }) => {
            const accKeyIndex: string = panelTitle || '';
            acc[accKeyIndex] = acc[accKeyIndex] ?? [];
            acc[accKeyIndex].push(text);
            return acc;
        }, {});

    const parseAttackDetail = (
        detail: any, 
        plainValues: ParsedDetailType = [], 
        panelTitle: string = ''
        ) => {
        for(const [label, value] of Object.entries(detail)) {
            if (typeof value === 'object') {
                parseAttackDetail(value, plainValues, label);
            }
            else {
                const text = `${label}: ${value}`;
                plainValues.push(panelTitle ? { panelTitle, text } : { text });
            }
        }

        const paragraphTexts = getParagraphTexts(plainValues);

        const collapsePanels = getCollapsePanels(plainValues);

        return { paragraphTexts, collapsePanels };
    }

    const renderAttackDetail = (detail: any) => {
        const { paragraphTexts, collapsePanels } = parseAttackDetail(detail);

        return (
            <div>
                <div style={{ marginLeft: '1%' }}>
                    { paragraphTexts.map((item, key) => <p key={ key }>{ item.text }</p>) }
                </div>
                <Collapse ghost>
                    {
                        Object.entries(collapsePanels).map(([header, texts], key) => {
                            const panelTexts = texts.map((text, pKey) => <p key={ pKey }>{ text }</p>);
                            return (
                                <Collapse.Panel header={ header } key={ key }>
                                    <div style={{ marginLeft: '1%' }}>{ panelTexts }</div>
                                </Collapse.Panel>
                            )
                        })
                    }
                </Collapse>
            </div>
        );
    }

    const parseDescriptionItemValue = (value: any) => {
        if (typeof value !== 'object') return value;
        else {
            return value instanceof Date ? value.toDateString() : renderAttackDetail(value);
        }
    }

    const getDescriptionItemSpan = (label: string) => {
        const labels = Object.keys(attack.parsedAttack);
        const numberOfFieldsWithGreaterThanOneSpanSize = (labels.length - 1) % 3;
        const oneBeforeTheDetailLabel = labels[labels.length - 2];

        const toBeThree: boolean = (
            numberOfFieldsWithGreaterThanOneSpanSize === 1 
            && label === oneBeforeTheDetailLabel)
            || label === 'Detail';

        const toBeTwo: boolean  = (
            numberOfFieldsWithGreaterThanOneSpanSize === 2 
            && label === oneBeforeTheDetailLabel
        )

        return toBeThree ? 3 : toBeTwo ? 2 : 1;
    }

    const renderAttackDetailDescription = () => {
        return (
            <Descriptions layout="vertical" bordered>
                {
                    Object.entries(attack.parsedAttack).map(([label, value], key) => {
                        return ( 
                            <Descriptions.Item 
                                label={ label } 
                                labelStyle={{ fontWeight: 'bold' }}
                                key={ key }
                                span={ getDescriptionItemSpan(label) }
                            > 
                                { parseDescriptionItemValue(value) } 
                            </Descriptions.Item>
                        )
                    })
                }       
            </Descriptions>
        )
    }

    const renderAttackDetailJson = () => {
        return (
            <Collapse>
                <Collapse.Panel header='JSON Format' key={1}>
                    <ReactJson 
                        src={ JSON.parse(attack.rawAttack) }
                        iconStyle='square'
                        displayDataTypes={ false }
                        displayObjectSize={ false }
                        enableClipboard={ false }
                        collapsed={ 1 }
                    />
                </Collapse.Panel>
            </Collapse>
        )
    }

    const fillComponentsWithAttackDetail = () => {
        return (
            <Space direction="vertical" size="middle" style={{ display: 'flex' }}>
                { renderAttackDetailDescription() }
                { renderAttackDetailJson() }
            </Space>
        )
    }

    const fillCardContent = () => {
        return pending ? <Spin /> : fillComponentsWithAttackDetail();
    }

    return (
        <Card title="Attack Detail">
            { fillCardContent() }
            <Button
                type="primary"
                ghost
                icon={<RollbackOutlined />}
                onClick={ () => navigate(-1) }
                style={{ marginTop: 5 }}
              >
                Back
              </Button>
        </Card>
    )
}
