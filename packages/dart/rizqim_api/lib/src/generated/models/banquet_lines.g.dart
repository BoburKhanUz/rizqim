// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'banquet_lines.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BanquetLines _$BanquetLinesFromJson(Map<String, dynamic> json) => BanquetLines(
  kind: json['kind'] as String,
  name: json['name'] as String,
  quantity: json['quantity'] as String,
  unit: json['unit'] as String,
  amount: json['amount'] as String,
);

Map<String, dynamic> _$BanquetLinesToJson(BanquetLines instance) =>
    <String, dynamic>{
      'kind': instance.kind,
      'name': instance.name,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'amount': instance.amount,
    };
