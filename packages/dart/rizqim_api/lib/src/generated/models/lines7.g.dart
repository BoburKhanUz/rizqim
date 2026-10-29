// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines7 _$Lines7FromJson(Map<String, dynamic> json) => Lines7(
  staffId: (json['staff_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  role: json['role'] as String?,
  basis: json['basis'] as String,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Lines7ToJson(Lines7 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'role': instance.role,
  'basis': instance.basis,
  'amount': instance.amount,
};
