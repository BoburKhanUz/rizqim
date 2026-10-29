// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'display.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Display _$DisplayFromJson(Map<String, dynamic> json) => Display(
  included: json['included'],
  unlimited: json['unlimited'] as bool,
  extraPrice: json['extra_price'] as String,
  canAdd: json['can_add'] as bool,
);

Map<String, dynamic> _$DisplayToJson(Display instance) => <String, dynamic>{
  'included': instance.included,
  'unlimited': instance.unlimited,
  'extra_price': instance.extraPrice,
  'can_add': instance.canAdd,
};
