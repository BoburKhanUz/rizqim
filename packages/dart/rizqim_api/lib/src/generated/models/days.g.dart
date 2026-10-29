// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'days.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Days _$DaysFromJson(Map<String, dynamic> json) => Days(
  businessDate: json['business_date'] as String,
  productId: json['product_id'] as String,
  productName: json['product_name'] as String,
  unit: json['unit'] as String,
  batches: json['batches'] as String,
  produced: json['produced'] as String,
  sold: json['sold'] as String,
  writtenOff: json['written_off'] as String,
  left: json['left'] as String,
  variance: json['variance'] as String,
  soldPercent: json['sold_percent'] as String?,
  writtenOffPercent: json['written_off_percent'] as String?,
);

Map<String, dynamic> _$DaysToJson(Days instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'unit': instance.unit,
  'batches': instance.batches,
  'produced': instance.produced,
  'sold': instance.sold,
  'written_off': instance.writtenOff,
  'left': instance.left,
  'variance': instance.variance,
  'sold_percent': instance.soldPercent,
  'written_off_percent': instance.writtenOffPercent,
};
