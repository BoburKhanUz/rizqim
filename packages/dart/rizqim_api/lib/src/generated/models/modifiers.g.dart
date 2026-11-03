// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers _$ModifiersFromJson(Map<String, dynamic> json) => Modifiers(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  groupId: (json['group_id'] as num).toInt(),
  groupName: json['group_name'] as String?,
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$ModifiersToJson(Modifiers instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'group_id': instance.groupId,
  'group_name': instance.groupName,
  'name': instance.name,
  'quantity': instance.quantity,
  'price_delta': instance.priceDelta,
};
