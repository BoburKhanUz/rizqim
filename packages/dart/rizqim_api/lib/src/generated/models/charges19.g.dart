// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'charges19.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Charges19 _$Charges19FromJson(Map<String, dynamic> json) => Charges19(
  itemsSubtotal: json['items_subtotal'] as String,
  discount: json['discount'] as String,
  serviceChargePercent: json['service_charge_percent'] as String,
  serviceChargeAmount: json['service_charge_amount'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  roomFeeAmount: json['room_fee_amount'] as String,
  roomTariffId: (json['room_tariff_id'] as num?)?.toInt(),
  businessDate: json['business_date'] as String?,
  dayType: json['day_type'] as String?,
  minSpend: json['min_spend'] as String,
  minSpendTopupAmount: json['min_spend_topup_amount'] as String,
  waived: (json['waived'] as List<dynamic>).map((e) => e as String).toList(),
  total: json['total'] as String,
  serviceChargeRule: json['service_charge_rule'] as String,
  placeFeeKind: json['place_fee_kind'] as String,
  serviceChargeNotice: json['service_charge_notice'] as String?,
  waivers: (json['waivers'] as List<dynamic>)
      .map((e) => Waivers14.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Charges19ToJson(Charges19 instance) => <String, dynamic>{
  'items_subtotal': instance.itemsSubtotal,
  'discount': instance.discount,
  'service_charge_percent': instance.serviceChargePercent,
  'service_charge_amount': instance.serviceChargeAmount,
  'place_fee_amount': instance.placeFeeAmount,
  'room_fee_amount': instance.roomFeeAmount,
  'room_tariff_id': instance.roomTariffId,
  'business_date': instance.businessDate,
  'day_type': instance.dayType,
  'min_spend': instance.minSpend,
  'min_spend_topup_amount': instance.minSpendTopupAmount,
  'waived': instance.waived,
  'total': instance.total,
  'service_charge_rule': instance.serviceChargeRule,
  'place_fee_kind': instance.placeFeeKind,
  'service_charge_notice': instance.serviceChargeNotice,
  'waivers': instance.waivers,
};
