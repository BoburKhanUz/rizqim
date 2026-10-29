// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_staff8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByStaff8 _$ByStaff8FromJson(Map<String, dynamic> json) => ByStaff8(
  staffId: (json['staff_id'] as num).toInt(),
  name: json['name'] as String?,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByStaff8ToJson(ByStaff8 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'amount': instance.amount,
};
