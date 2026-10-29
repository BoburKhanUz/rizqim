// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_staff.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByStaff _$ByStaffFromJson(Map<String, dynamic> json) => ByStaff(
  staffId: (json['staff_id'] as num).toInt(),
  name: json['name'] as String?,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByStaffToJson(ByStaff instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'amount': instance.amount,
};
