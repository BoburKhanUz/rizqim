// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_branch.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByBranch _$ByBranchFromJson(Map<String, dynamic> json) => ByBranch(
  branchId: (json['branch_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  orders: (json['orders'] as num).toInt(),
  delivered: (json['delivered'] as num).toInt(),
  revenue: json['revenue'] as String,
);

Map<String, dynamic> _$ByBranchToJson(ByBranch instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'name': instance.name,
  'orders': instance.orders,
  'delivered': instance.delivered,
  'revenue': instance.revenue,
};
