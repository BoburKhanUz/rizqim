// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'blocks2.dart';

part 'data260.g.dart';

@JsonSerializable()
class Data260 {
  const Data260({
    required this.numbers,
    required this.calling,
    required this.businessDate,
    required this.blockSize,
    required this.lowWater,
    required this.blocks,
    required this.nextNumber,
  });

  factory Data260.fromJson(Map<String, Object?> json) =>
      _$Data260FromJson(json);

  final bool numbers;
  final bool calling;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'block_size')
  final int blockSize;
  @JsonKey(name: 'low_water')
  final int lowWater;
  final List<Blocks2> blocks;
  @JsonKey(name: 'next_number')
  final int? nextNumber;

  Map<String, Object?> toJson() => _$Data260ToJson(this);
}
