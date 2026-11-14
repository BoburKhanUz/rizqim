// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data334.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data334 _$Data334FromJson(Map<String, dynamic> json) => Data334(
  numbers: json['numbers'] as bool,
  calling: json['calling'] as bool,
  businessDate: json['business_date'] as String,
  blockSize: (json['block_size'] as num).toInt(),
  lowWater: (json['low_water'] as num).toInt(),
  blocks: (json['blocks'] as List<dynamic>)
      .map((e) => Blocks2.fromJson(e as Map<String, dynamic>))
      .toList(),
  nextNumber: (json['next_number'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data334ToJson(Data334 instance) => <String, dynamic>{
  'numbers': instance.numbers,
  'calling': instance.calling,
  'business_date': instance.businessDate,
  'block_size': instance.blockSize,
  'low_water': instance.lowWater,
  'blocks': instance.blocks,
  'next_number': instance.nextNumber,
};
