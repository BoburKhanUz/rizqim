// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_payment_method.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByPaymentMethod _$ByPaymentMethodFromJson(Map<String, dynamic> json) =>
    ByPaymentMethod(
      method: json['method'] as String,
      orders: (json['orders'] as num).toInt(),
      revenue: json['revenue'] as num,
    );

Map<String, dynamic> _$ByPaymentMethodToJson(ByPaymentMethod instance) =>
    <String, dynamic>{
      'method': instance.method,
      'orders': instance.orders,
      'revenue': instance.revenue,
    };
