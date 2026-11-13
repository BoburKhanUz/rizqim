// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_tender.dart';

part 'sold.g.dart';

@JsonSerializable()
class Sold {
  const Sold({
    required this.count,
    required this.amount,
    required this.byTender,
  });

  factory Sold.fromJson(Map<String, Object?> json) => _$SoldFromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_tender')
  final List<ByTender> byTender;

  Map<String, Object?> toJson() => _$SoldToJson(this);
}
