// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines5 _$Lines5FromJson(Map<String, dynamic> json) => Lines5(
  staffId: (json['staff_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  role: json['role'] as String?,
  basis: json['basis'] as String,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Lines5ToJson(Lines5 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'role': instance.role,
  'basis': instance.basis,
  'amount': instance.amount,
};
