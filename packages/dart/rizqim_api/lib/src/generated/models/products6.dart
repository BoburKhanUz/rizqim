// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'products6.g.dart';

@JsonSerializable()
class Products6 {
  const Products6({
    required this.productId,
    required this.name,
    required this.count,
    required this.medianSeconds,
    required this.p90Seconds,
    required this.withTarget,
    required this.overTarget,
    required this.overTargetShare,
  });

  factory Products6.fromJson(Map<String, Object?> json) =>
      _$Products6FromJson(json);

  @JsonKey(name: 'product_id')
  final int? productId;
  final String name;
  final int count;
  @JsonKey(name: 'median_seconds')
  final int? medianSeconds;
  @JsonKey(name: 'p90_seconds')
  final int? p90Seconds;
  @JsonKey(name: 'with_target')
  final int withTarget;
  @JsonKey(name: 'over_target')
  final int overTarget;
  @JsonKey(name: 'over_target_share')
  final String? overTargetShare;

  Map<String, Object?> toJson() => _$Products6ToJson(this);
}
