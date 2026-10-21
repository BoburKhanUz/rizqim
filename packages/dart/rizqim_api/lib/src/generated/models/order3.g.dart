// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Order3 _$Order3FromJson(Map<String, dynamic> json) => Order3(
  uuid: json['uuid'] as String,
  shortCode: json['short_code'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  totalPrice: json['total_price'] as String,
);

Map<String, dynamic> _$Order3ToJson(Order3 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'short_code': instance.shortCode,
  'branch_id': instance.branchId,
  'total_price': instance.totalPrice,
};
