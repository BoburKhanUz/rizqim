// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'kds.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Kds _$KdsFromJson(Map<String, dynamic> json) => Kds(
  included: (json['included'] as num?)?.toInt(),
  active: (json['active'] as num).toInt(),
  extra: (json['extra'] as num).toInt(),
  extraPrice: json['extra_price'] as String?,
  canAdd: json['can_add'] as bool,
);

Map<String, dynamic> _$KdsToJson(Kds instance) => <String, dynamic>{
  'included': instance.included,
  'active': instance.active,
  'extra': instance.extra,
  'extra_price': instance.extraPrice,
  'can_add': instance.canAdd,
};
