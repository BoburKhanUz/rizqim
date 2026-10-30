// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'split.g.dart';

@JsonSerializable()
class Split {
  const Split({
    required this.name,
    required this.mxikCode,
    required this.quantity,
    required this.amount,
  });

  factory Split.fromJson(Map<String, Object?> json) => _$SplitFromJson(json);

  final String name;
  @JsonKey(name: 'mxik_code')
  final String? mxikCode;
  final int quantity;
  final String amount;

  Map<String, Object?> toJson() => _$SplitToJson(this);
}
