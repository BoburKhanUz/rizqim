// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_tender2.g.dart';

@JsonSerializable()
class ByTender2 {
  const ByTender2({
    required this.tender,
    required this.count,
    required this.amount,
  });

  factory ByTender2.fromJson(Map<String, Object?> json) =>
      _$ByTender2FromJson(json);

  final String tender;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$ByTender2ToJson(this);
}
