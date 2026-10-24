// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'alerts3.g.dart';

@JsonSerializable()
class Alerts3 {
  const Alerts3({
    this.openRoomHours,
    this.voidAmount,
    this.discountPercent,
    this.unacceptedOnlineMinutes,
    this.cashOutAmount,
  });

  factory Alerts3.fromJson(Map<String, Object?> json) =>
      _$Alerts3FromJson(json);

  @JsonKey(name: 'open_room_hours')
  final int? openRoomHours;
  @JsonKey(name: 'void_amount')
  final num? voidAmount;
  @JsonKey(name: 'discount_percent')
  final num? discountPercent;
  @JsonKey(name: 'unaccepted_online_minutes')
  final int? unacceptedOnlineMinutes;
  @JsonKey(name: 'cash_out_amount')
  final num? cashOutAmount;

  Map<String, Object?> toJson() => _$Alerts3ToJson(this);
}
