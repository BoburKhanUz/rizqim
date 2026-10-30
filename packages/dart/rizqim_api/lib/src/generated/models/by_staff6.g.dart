// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_staff6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByStaff6 _$ByStaff6FromJson(Map<String, dynamic> json) => ByStaff6(
  staffId: (json['staff_id'] as num).toInt(),
  name: json['name'] as String?,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByStaff6ToJson(ByStaff6 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'amount': instance.amount,
};
