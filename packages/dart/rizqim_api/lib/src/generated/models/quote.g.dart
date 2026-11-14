// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'quote.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Quote _$QuoteFromJson(Map<String, dynamic> json) => Quote(
  currency: json['currency'] as String,
  rate: json['rate'] as String,
  total: json['total'] as String,
);

Map<String, dynamic> _$QuoteToJson(Quote instance) => <String, dynamic>{
  'currency': instance.currency,
  'rate': instance.rate,
  'total': instance.total,
};
