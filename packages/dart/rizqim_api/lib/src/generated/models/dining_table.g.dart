// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'dining_table.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DiningTable _$DiningTableFromJson(Map<String, dynamic> json) => DiningTable(
  id: (json['id'] as num).toInt(),
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  hallId: (json['hall_id'] as num).toInt(),
  name: json['name'] as String,
  kind: DiningTableKind.fromJson(json['kind'] as String),
  seats: (json['seats'] as num?)?.toInt(),
  layoutX: json['layout_x'] as String?,
  layoutY: json['layout_y'] as String?,
  layoutW: json['layout_w'] as String?,
  layoutH: json['layout_h'] as String?,
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  minSpend: json['min_spend'] as String?,
  minSpendMode: MinSpendMode.fromJson(json['min_spend_mode'] as String?),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  deletedAt: json['deleted_at'] == null
      ? null
      : DateTime.parse(json['deleted_at'] as String),
  serviceState: json['service_state'] as String?,
  serviceStateAt: json['service_state_at'] == null
      ? null
      : DateTime.parse(json['service_state_at'] as String),
  serviceStateBy: (json['service_state_by'] as num?)?.toInt(),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch.fromJson(json['branch'] as Map<String, dynamic>),
  hall: json['hall'] == null
      ? null
      : Hall.fromJson(json['hall'] as Map<String, dynamic>),
  openOrders: (json['open_orders'] as List<dynamic>?)
      ?.map((e) => Order.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$DiningTableToJson(DiningTable instance) =>
    <String, dynamic>{
      'id': instance.id,
      'store_id': instance.storeId,
      'branch_id': instance.branchId,
      'hall_id': instance.hallId,
      'name': instance.name,
      'kind': instance.kind,
      'seats': instance.seats,
      'layout_x': instance.layoutX,
      'layout_y': instance.layoutY,
      'layout_w': instance.layoutW,
      'layout_h': instance.layoutH,
      'sort_order': instance.sortOrder,
      'is_active': instance.isActive,
      'min_spend': instance.minSpend,
      'min_spend_mode': instance.minSpendMode,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'deleted_at': instance.deletedAt?.toIso8601String(),
      'service_state': instance.serviceState,
      'service_state_at': instance.serviceStateAt?.toIso8601String(),
      'service_state_by': instance.serviceStateBy,
      'store': instance.store,
      'branch': instance.branch,
      'hall': instance.hall,
      'open_orders': instance.openOrders,
    };
