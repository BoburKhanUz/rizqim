// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers3 _$Modifiers3FromJson(Map<String, dynamic> json) => Modifiers3(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  groupId: (json['group_id'] as num).toInt(),
  groupName: json['group_name'] as String?,
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$Modifiers3ToJson(Modifiers3 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'uuid': instance.uuid,
      'group_id': instance.groupId,
      'group_name': instance.groupName,
      'name': instance.name,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
