// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'couriers.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Couriers _$CouriersFromJson(Map<String, dynamic> json) => Couriers(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  deliveredCount: (json['delivered_count'] as num).toInt(),
);

Map<String, dynamic> _$CouriersToJson(Couriers instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'delivered_count': instance.deliveredCount,
};
