// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pos.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Pos _$PosFromJson(Map<String, dynamic> json) => Pos(
  included: (json['included'] as num?)?.toInt(),
  active: (json['active'] as num).toInt(),
  extra: (json['extra'] as num).toInt(),
  extraPrice: json['extra_price'] as String?,
  canAdd: json['can_add'] as bool,
);

Map<String, dynamic> _$PosToJson(Pos instance) => <String, dynamic>{
  'included': instance.included,
  'active': instance.active,
  'extra': instance.extra,
  'extra_price': instance.extraPrice,
  'can_add': instance.canAdd,
};
