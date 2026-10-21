// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_payment_method2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByPaymentMethod2 _$ByPaymentMethod2FromJson(Map<String, dynamic> json) =>
    ByPaymentMethod2(
      method: json['method'] as String,
      orders: (json['orders'] as num).toInt(),
      revenue: json['revenue'] as String,
    );

Map<String, dynamic> _$ByPaymentMethod2ToJson(ByPaymentMethod2 instance) =>
    <String, dynamic>{
      'method': instance.method,
      'orders': instance.orders,
      'revenue': instance.revenue,
    };
