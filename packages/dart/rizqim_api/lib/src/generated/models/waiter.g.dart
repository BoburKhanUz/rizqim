// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'waiter.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Waiter _$WaiterFromJson(Map<String, dynamic> json) => Waiter(
  included: json['included'],
  unlimited: json['unlimited'] as bool,
  extraPrice: json['extra_price'] as String,
  canAdd: json['can_add'] as bool,
);

Map<String, dynamic> _$WaiterToJson(Waiter instance) => <String, dynamic>{
  'included': instance.included,
  'unlimited': instance.unlimited,
  'extra_price': instance.extraPrice,
  'can_add': instance.canAdd,
};
