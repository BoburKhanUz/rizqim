// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payment_settings2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PaymentSettings2 _$PaymentSettings2FromJson(Map<String, dynamic> json) =>
    PaymentSettings2(
      deliveryFee: json['delivery_fee'] as num?,
      deliveryEnabled: json['delivery_enabled'] as bool?,
      pickupEnabled: json['pickup_enabled'] as bool?,
      workingHours: json['working_hours'] == null
          ? null
          : WorkingHours6.fromJson(
              json['working_hours'] as Map<String, dynamic>,
            ),
      cash: json['cash'] == null
          ? null
          : Cash.fromJson(json['cash'] as Map<String, dynamic>),
      click: json['click'] == null
          ? null
          : Click.fromJson(json['click'] as Map<String, dynamic>),
      payme: json['payme'] == null
          ? null
          : Payme.fromJson(json['payme'] as Map<String, dynamic>),
      uzum: json['uzum'] == null
          ? null
          : Uzum.fromJson(json['uzum'] as Map<String, dynamic>),
      paynet: json['paynet'] == null
          ? null
          : Paynet.fromJson(json['paynet'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PaymentSettings2ToJson(PaymentSettings2 instance) =>
    <String, dynamic>{
      'delivery_fee': instance.deliveryFee,
      'delivery_enabled': instance.deliveryEnabled,
      'pickup_enabled': instance.pickupEnabled,
      'working_hours': instance.workingHours,
      'cash': instance.cash,
      'click': instance.click,
      'payme': instance.payme,
      'uzum': instance.uzum,
      'paynet': instance.paynet,
    };
