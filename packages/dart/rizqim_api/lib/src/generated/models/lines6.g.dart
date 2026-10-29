// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines6 _$Lines6FromJson(Map<String, dynamic> json) => Lines6(
  staffId: (json['staff_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  role: json['role'] as String?,
  basis: json['basis'] as String,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Lines6ToJson(Lines6 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'role': instance.role,
  'basis': instance.basis,
  'amount': instance.amount,
};
