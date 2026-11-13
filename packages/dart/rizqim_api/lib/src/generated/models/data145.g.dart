// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data145.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data145 _$Data145FromJson(Map<String, dynamic> json) => Data145(
  from: json['from'] as String,
  to: json['to'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
  sold: Sold.fromJson(json['sold'] as Map<String, dynamic>),
  redeemed: Redeemed.fromJson(json['redeemed'] as Map<String, dynamic>),
  refunded: Refunded.fromJson(json['refunded'] as Map<String, dynamic>),
  netChange: json['net_change'] as String,
  outstanding: Outstanding.fromJson(
    json['outstanding'] as Map<String, dynamic>,
  ),
  blocked: Blocked2.fromJson(json['blocked'] as Map<String, dynamic>),
  expired: Expired.fromJson(json['expired'] as Map<String, dynamic>),
  asOf: json['as_of'] as String,
);

Map<String, dynamic> _$Data145ToJson(Data145 instance) => <String, dynamic>{
  'from': instance.from,
  'to': instance.to,
  'branch_id': instance.branchId,
  'sold': instance.sold,
  'redeemed': instance.redeemed,
  'refunded': instance.refunded,
  'net_change': instance.netChange,
  'outstanding': instance.outstanding,
  'blocked': instance.blocked,
  'expired': instance.expired,
  'as_of': instance.asOf,
};
