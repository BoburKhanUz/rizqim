// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payment_settings.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PaymentSettings _$PaymentSettingsFromJson(Map<String, dynamic> json) =>
    PaymentSettings(
      cash: json['cash'],
      click: json['click'],
      payme: json['payme'],
      uzum: json['uzum'],
      paynet: json['paynet'],
      deliveryFee: json['delivery_fee'],
      deliveryEnabled: json['delivery_enabled'] as bool?,
      pickupEnabled: json['pickup_enabled'] as bool?,
      workingHours: json['working_hours'] == null
          ? null
          : WorkingHours5.fromJson(
              json['working_hours'] as Map<String, dynamic>,
            ),
      allowedMethods: (json['allowed_methods'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
    );

Map<String, dynamic> _$PaymentSettingsToJson(PaymentSettings instance) =>
    <String, dynamic>{
      'cash': instance.cash,
      'click': instance.click,
      'payme': instance.payme,
      'uzum': instance.uzum,
      'paynet': instance.paynet,
      'delivery_fee': instance.deliveryFee,
      'delivery_enabled': instance.deliveryEnabled,
      'pickup_enabled': instance.pickupEnabled,
      'working_hours': instance.workingHours,
      'allowed_methods': instance.allowedMethods,
    };
