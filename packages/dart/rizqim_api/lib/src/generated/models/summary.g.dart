// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'summary.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Summary _$SummaryFromJson(Map<String, dynamic> json) => Summary(
  ordersCount: (json['orders_count'] as num).toInt(),
  deliveredCount: (json['delivered_count'] as num).toInt(),
  canceledCount: (json['canceled_count'] as num).toInt(),
  refundedCount: (json['refunded_count'] as num).toInt(),
  activeCount: (json['active_count'] as num).toInt(),
  revenue: json['revenue'] as num,
  averageCheck: json['average_check'] as num,
);

Map<String, dynamic> _$SummaryToJson(Summary instance) => <String, dynamic>{
  'orders_count': instance.ordersCount,
  'delivered_count': instance.deliveredCount,
  'canceled_count': instance.canceledCount,
  'refunded_count': instance.refundedCount,
  'active_count': instance.activeCount,
  'revenue': instance.revenue,
  'average_check': instance.averageCheck,
};
