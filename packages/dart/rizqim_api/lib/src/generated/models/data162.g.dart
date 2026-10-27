// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data162.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data162 _$Data162FromJson(Map<String, dynamic> json) => Data162(
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  phone: json['phone'] as String?,
  groupName: json['group_name'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  creditLimit: json['credit_limit'] as String?,
  balance: json['balance'] as String,
  availableCredit: json['available_credit'] as String?,
  isBlocked: json['is_blocked'] as bool,
  note: json['note'] as String?,
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Data162ToJson(Data162 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'phone': instance.phone,
  'group_name': instance.groupName,
  'branch_id': instance.branchId,
  'credit_limit': instance.creditLimit,
  'balance': instance.balance,
  'available_credit': instance.availableCredit,
  'is_blocked': instance.isBlocked,
  'note': instance.note,
  'created_at': instance.createdAt,
  'updated_at': instance.updatedAt,
};
