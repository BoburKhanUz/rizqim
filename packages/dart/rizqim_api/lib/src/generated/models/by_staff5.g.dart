// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_staff5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByStaff5 _$ByStaff5FromJson(Map<String, dynamic> json) => ByStaff5(
  staffId: (json['staff_id'] as num).toInt(),
  name: json['name'] as String?,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByStaff5ToJson(ByStaff5 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'amount': instance.amount,
};
