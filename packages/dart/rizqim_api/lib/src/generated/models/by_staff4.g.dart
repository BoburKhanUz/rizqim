// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_staff4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByStaff4 _$ByStaff4FromJson(Map<String, dynamic> json) => ByStaff4(
  staffId: (json['staff_id'] as num).toInt(),
  name: json['name'] as String?,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByStaff4ToJson(ByStaff4 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'amount': instance.amount,
};
