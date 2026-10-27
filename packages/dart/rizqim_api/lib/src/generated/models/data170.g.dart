// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data170.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data170 _$Data170FromJson(Map<String, dynamic> json) => Data170(
  branchId: (json['branch_id'] as num).toInt(),
  generatedAt: json['generated_at'] as String,
  overdueAfterHours: (json['overdue_after_hours'] as num).toInt(),
  halls: (json['halls'] as List<dynamic>)
      .map((e) => Halls2.fromJson(e as Map<String, dynamic>))
      .toList(),
  untabledOrders: (json['untabled_orders'] as List<dynamic>)
      .map((e) => UntabledOrders.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data170ToJson(Data170 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'generated_at': instance.generatedAt,
  'overdue_after_hours': instance.overdueAfterHours,
  'halls': instance.halls,
  'untabled_orders': instance.untabledOrders,
};
