// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_tender5.g.dart';

@JsonSerializable()
class ByTender5 {
  const ByTender5({
    required this.tender,
    required this.count,
    required this.amount,
  });

  factory ByTender5.fromJson(Map<String, Object?> json) =>
      _$ByTender5FromJson(json);

  final String tender;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$ByTender5ToJson(this);
}
