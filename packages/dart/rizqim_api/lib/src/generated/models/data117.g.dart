// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data117.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data117 _$Data117FromJson(Map<String, dynamic> json) => Data117(
  period: Period2.fromJson(json['period'] as Map<String, dynamic>),
  branchId: (json['branch_id'] as num?)?.toInt(),
  summary: Summary2.fromJson(json['summary'] as Map<String, dynamic>),
  byStatus: Map<String, int>.from(json['by_status'] as Map),
  series: (json['series'] as List<dynamic>)
      .map((e) => Series.fromJson(e as Map<String, dynamic>))
      .toList(),
  byChannel: (json['by_channel'] as List<dynamic>)
      .map((e) => ByChannel2.fromJson(e as Map<String, dynamic>))
      .toList(),
  byBranch: (json['by_branch'] as List<dynamic>)
      .map((e) => ByBranch.fromJson(e as Map<String, dynamic>))
      .toList(),
  byPaymentMethod: (json['by_payment_method'] as List<dynamic>)
      .map((e) => ByPaymentMethod2.fromJson(e as Map<String, dynamic>))
      .toList(),
  byOrderType: (json['by_order_type'] as List<dynamic>)
      .map((e) => ByOrderType2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data117ToJson(Data117 instance) => <String, dynamic>{
  'period': instance.period,
  'branch_id': instance.branchId,
  'summary': instance.summary,
  'by_status': instance.byStatus,
  'series': instance.series,
  'by_channel': instance.byChannel,
  'by_branch': instance.byBranch,
  'by_payment_method': instance.byPaymentMethod,
  'by_order_type': instance.byOrderType,
};
