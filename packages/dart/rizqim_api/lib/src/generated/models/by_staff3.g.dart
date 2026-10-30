// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_staff3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByStaff3 _$ByStaff3FromJson(Map<String, dynamic> json) => ByStaff3(
  staffId: (json['staff_id'] as num).toInt(),
  name: json['name'] as String?,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByStaff3ToJson(ByStaff3 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'amount': instance.amount,
};
