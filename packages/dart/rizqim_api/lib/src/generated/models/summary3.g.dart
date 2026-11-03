// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'summary3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Summary3 _$Summary3FromJson(Map<String, dynamic> json) => Summary3(
  ordersCount: (json['orders_count'] as num).toInt(),
  deliveredCount: (json['delivered_count'] as num).toInt(),
  canceledCount: (json['canceled_count'] as num).toInt(),
  refundedCount: (json['refunded_count'] as num).toInt(),
  activeCount: (json['active_count'] as num).toInt(),
  revenue: json['revenue'] as String,
  averageCheck: json['average_check'] as String,
);

Map<String, dynamic> _$Summary3ToJson(Summary3 instance) => <String, dynamic>{
  'orders_count': instance.ordersCount,
  'delivered_count': instance.deliveredCount,
  'canceled_count': instance.canceledCount,
  'refunded_count': instance.refundedCount,
  'active_count': instance.activeCount,
  'revenue': instance.revenue,
  'average_check': instance.averageCheck,
};
