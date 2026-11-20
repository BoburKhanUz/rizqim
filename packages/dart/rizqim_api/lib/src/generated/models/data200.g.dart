// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data200.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data200 _$Data200FromJson(Map<String, dynamic> json) => Data200(
  period: json['period'] as String,
  periodLabel: json['period_label'] as String,
  salesAttribution: json['sales_attribution'] as String,
  salesAttributionLabel: json['sales_attribution_label'] as String,
);

Map<String, dynamic> _$Data200ToJson(Data200 instance) => <String, dynamic>{
  'period': instance.period,
  'period_label': instance.periodLabel,
  'sales_attribution': instance.salesAttribution,
  'sales_attribution_label': instance.salesAttributionLabel,
};
