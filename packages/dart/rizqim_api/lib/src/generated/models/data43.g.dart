// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data43.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data43 _$Data43FromJson(Map<String, dynamic> json) => Data43(
  branchId: (json['branch_id'] as num).toInt(),
  businessDay: BusinessDay.fromJson(
    json['business_day'] as Map<String, dynamic>,
  ),
  days: (json['days'] as List<dynamic>)
      .map((e) => Days2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data43ToJson(Data43 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'business_day': instance.businessDay,
  'days': instance.days,
};
