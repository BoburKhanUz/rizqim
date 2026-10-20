// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data7 _$Data7FromJson(Map<String, dynamic> json) => Data7(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  phone: json['phone'] as String?,
  telegramUsername: json['telegram_username'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  courierType: json['courier_type'] as String,
);

Map<String, dynamic> _$Data7ToJson(Data7 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'phone': instance.phone,
  'telegram_username': instance.telegramUsername,
  'branch_id': instance.branchId,
  'courier_type': instance.courierType,
};
