// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'times.g.dart';

@JsonSerializable()
class Times {
  const Times({
    required this.averagePrepSeconds,
    required this.averageDeliverySeconds,
  });

  factory Times.fromJson(Map<String, Object?> json) => _$TimesFromJson(json);

  @JsonKey(name: 'average_prep_seconds')
  final int? averagePrepSeconds;
  @JsonKey(name: 'average_delivery_seconds')
  final int? averageDeliverySeconds;

  Map<String, Object?> toJson() => _$TimesToJson(this);
}
