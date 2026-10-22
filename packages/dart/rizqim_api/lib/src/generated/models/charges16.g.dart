// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'charges16.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Charges16 _$Charges16FromJson(Map<String, dynamic> json) => Charges16(
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
  waivers: (json['waivers'] as List<dynamic>)
      .map((e) => Waivers11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Charges16ToJson(Charges16 instance) => <String, dynamic>{
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
  'waivers': instance.waivers,
};
