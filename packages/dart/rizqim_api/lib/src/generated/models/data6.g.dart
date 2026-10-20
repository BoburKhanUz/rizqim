// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data6 _$Data6FromJson(Map<String, dynamic> json) => Data6(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  phone: json['phone'] as String?,
  telegramUsername: json['telegram_username'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  courierType: json['courier_type'] as String,
);

Map<String, dynamic> _$Data6ToJson(Data6 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'phone': instance.phone,
  'telegram_username': instance.telegramUsername,
  'branch_id': instance.branchId,
  'courier_type': instance.courierType,
};
