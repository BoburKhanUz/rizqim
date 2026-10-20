// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data24 _$Data24FromJson(Map<String, dynamic> json) => Data24(
  period: Period.fromJson(json['period'] as String?),
  summary: Summary.fromJson(json['summary'] as Map<String, dynamic>),
  byStatus: Map<String, int>.from(json['by_status'] as Map),
  byPaymentMethod: (json['by_payment_method'] as List<dynamic>)
      .map((e) => ByPaymentMethod.fromJson(e as Map<String, dynamic>))
      .toList(),
  byOrderType: (json['by_order_type'] as List<dynamic>)
      .map((e) => ByOrderType.fromJson(e as Map<String, dynamic>))
      .toList(),
  byChannel: (json['by_channel'] as List<dynamic>)
      .map((e) => ByChannel.fromJson(e as Map<String, dynamic>))
      .toList(),
  topProducts: (json['top_products'] as List<dynamic>)
      .map((e) => TopProducts.fromJson(e as Map<String, dynamic>))
      .toList(),
  couriers: (json['couriers'] as List<dynamic>)
      .map((e) => Couriers.fromJson(e as Map<String, dynamic>))
      .toList(),
  times: Times.fromJson(json['times'] as Map<String, dynamic>),
  daily: (json['daily'] as List<dynamic>)
      .map((e) => Daily.fromJson(e as Map<String, dynamic>))
      .toList(),
  generatedAt: json['generated_at'] as String,
);

Map<String, dynamic> _$Data24ToJson(Data24 instance) => <String, dynamic>{
  'period': instance.period,
  'summary': instance.summary,
  'by_status': instance.byStatus,
  'by_payment_method': instance.byPaymentMethod,
  'by_order_type': instance.byOrderType,
  'by_channel': instance.byChannel,
  'top_products': instance.topProducts,
  'couriers': instance.couriers,
  'times': instance.times,
  'daily': instance.daily,
  'generated_at': instance.generatedAt,
};
