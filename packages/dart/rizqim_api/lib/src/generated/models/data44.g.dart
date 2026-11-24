// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data44.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data44 _$Data44FromJson(Map<String, dynamic> json) => Data44(
  branchId: (json['branch_id'] as num).toInt(),
  businessDay: BusinessDay.fromJson(
    json['business_day'] as Map<String, dynamic>,
  ),
  days: (json['days'] as List<dynamic>)
      .map((e) => Days2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data44ToJson(Data44 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'business_day': instance.businessDay,
  'days': instance.days,
};
