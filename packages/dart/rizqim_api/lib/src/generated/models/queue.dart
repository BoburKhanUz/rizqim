// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'blocks.dart';

part 'queue.g.dart';

@JsonSerializable()
class Queue {
  const Queue({
    required this.numbers,
    required this.calling,
    required this.businessDate,
    required this.blockSize,
    required this.lowWater,
    required this.blocks,
    required this.nextNumber,
  });

  factory Queue.fromJson(Map<String, Object?> json) => _$QueueFromJson(json);

  final bool numbers;
  final bool calling;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'block_size')
  final int blockSize;
  @JsonKey(name: 'low_water')
  final int lowWater;
  final List<Blocks> blocks;
  @JsonKey(name: 'next_number')
  final int? nextNumber;

  Map<String, Object?> toJson() => _$QueueToJson(this);
}
