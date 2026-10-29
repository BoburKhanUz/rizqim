// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_staff7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByStaff7 _$ByStaff7FromJson(Map<String, dynamic> json) => ByStaff7(
  staffId: (json['staff_id'] as num).toInt(),
  name: json['name'] as String?,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByStaff7ToJson(ByStaff7 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'amount': instance.amount,
};
