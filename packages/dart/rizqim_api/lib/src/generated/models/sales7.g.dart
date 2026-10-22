// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sales7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sales7 _$Sales7FromJson(Map<String, dynamic> json) => Sales7(
  bills: (json['bills'] as num).toInt(),
  received: json['received'] as String,
  refunded: json['refunded'] as String,
  net: json['net'] as String,
  billsTotal: json['bills_total'] as String,
  subtotal: json['subtotal'] as String,
  discount: json['discount'] as String,
  roomFee: json['room_fee'] as String,
  minSpendTopup: json['min_spend_topup'] as String,
  serviceCharge: json['service_charge'] as String,
  placeFee: json['place_fee'] as String,
  averageBill: json['average_bill'] as String,
);

Map<String, dynamic> _$Sales7ToJson(Sales7 instance) => <String, dynamic>{
  'bills': instance.bills,
  'received': instance.received,
  'refunded': instance.refunded,
  'net': instance.net,
  'bills_total': instance.billsTotal,
  'subtotal': instance.subtotal,
  'discount': instance.discount,
  'room_fee': instance.roomFee,
  'min_spend_topup': instance.minSpendTopup,
  'service_charge': instance.serviceCharge,
  'place_fee': instance.placeFee,
  'average_bill': instance.averageBill,
};
