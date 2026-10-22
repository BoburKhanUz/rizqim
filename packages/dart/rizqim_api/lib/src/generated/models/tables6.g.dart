// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tables6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tables6 _$Tables6FromJson(Map<String, dynamic> json) => Tables6(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String,
  seats: (json['seats'] as num?)?.toInt(),
  layout: Layout12.fromJson(json['layout'] as Map<String, dynamic>),
  isActive: json['is_active'] as bool,
  minSpend: json['min_spend'] as String?,
  minSpendMode: json['min_spend_mode'] as String,
  status: json['status'] as String,
  billRequested: json['bill_requested'] as bool,
  reservation: json['reservation'] == null
      ? null
      : Reservation.fromJson(json['reservation'] as Map<String, dynamic>),
  orders: (json['orders'] as List<dynamic>)
      .map((e) => Orders.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Tables6ToJson(Tables6 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'kind': instance.kind,
  'seats': instance.seats,
  'layout': instance.layout,
  'is_active': instance.isActive,
  'min_spend': instance.minSpend,
  'min_spend_mode': instance.minSpendMode,
  'status': instance.status,
  'bill_requested': instance.billRequested,
  'reservation': instance.reservation,
  'orders': instance.orders,
};
